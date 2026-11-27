<?php

// Keep individual statements below the default XAMPP packet size.
const MYPARK_DATABASE_PART_BYTES = 256 * 1024;

function mypark_database(): PDO {
    static $database;
    if ($database instanceof PDO) return $database;

    $config = require dirname(__DIR__) . '/config/database.php';
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
    $database = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 4,
    ]);
    $database->exec('SET SESSION innodb_lock_wait_timeout = 10');
    return $database;
}

/** Keep an individual operation atomic within the request transaction. */
function mypark_database_action(PDO $database, callable $operation): mixed {
    $database->exec('SAVEPOINT mypark_action');
    try {
        $result = $operation();
        $database->exec('RELEASE SAVEPOINT mypark_action');
        return $result;
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->exec('ROLLBACK TO SAVEPOINT mypark_action');
            $database->exec('RELEASE SAVEPOINT mypark_action');
        }
        throw $error;
    }
}

function mypark_database_error(): void {
    error_log('MyPark database unavailable. Check the MariaDB connection and the imported database.sql schema.');
    http_response_code(503);
    header_remove('Location');
    header_remove('Content-Disposition');
    header_remove('Content-Length');
    header('Cache-Control: no-store');
    $message = 'MyPark could not save or load records. Check the XAMPP database and try again.';
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message, 'message' => $message]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
}

final class MyParkData {
    private const COLLECTIONS = [
        'demo_users', 'demo_lots', 'demo_bookings', 'demo_wallets', 'demo_receipts', 'demo_reviews',
        'staff_fines', 'staff_maintenance', 'staff_audit', 'staff_counters', 'staff_requests', 'staff_leave_requests',
        'oku_submissions', 'oku_latest', 'parking_notifications', 'parking_zone_notice_at',
        'pending_registrations',
    ];
    private const COUNTERS = ['last_booking_id', 'ops_revision', 'staff_revision'];
    private const LEGACY_KEYS = [
        'demo_wallet_balance', 'demo_wallet_transactions', 'vehicle_account_scope', 'saved_vehicles', 'default_vehicle_ids',
    ];

    public static array $records = [];
    private static string $workspaceId;

    public static function workspace(): string {
        return self::$workspaceId;
    }

    public static function start(): void {
        $session = $_SESSION;
        $legacy = isset($session['demo_users'], $session['demo_lots']);
        $oldScope = $session['vehicle_account_scope'] ?? '';
        $workspace = self::sessionWorkspace($session);

        try {
            $database = mypark_database();
            $database->beginTransaction();
            // The workspace row serializes mutations from different browsers, including
            // booking availability, generated IDs, and wallet balance changes.
            $lock = $database->prepare('INSERT INTO mypark_workspaces (id) VALUES (?)
                ON DUPLICATE KEY UPDATE id = id');
            $lock->execute([$workspace]);
            $newWorkspace = $lock->rowCount() === 1;
            self::$workspaceId = $workspace;
            $original = self::load($database, $workspace);
            self::$records = $original;

            if ($newWorkspace && $legacy) {
                $keys = [...self::COLLECTIONS, ...self::COUNTERS];
                self::$records = array_intersect_key($session, array_fill_keys($keys, true));
                $owner = $session['user']['id'] ?? null;
                if ($owner !== null && isset($session['demo_wallet_balance'])) {
                    self::$records['demo_wallets'][$owner] ??= [
                        'balance' => $session['demo_wallet_balance'],
                        'transactions' => $session['demo_wallet_transactions'] ?? [],
                    ];
                }
                require_once __DIR__ . '/database-migration.php';
                mypark_import_legacy($database, $oldScope, $session);
            }
        } catch (Throwable $error) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            mypark_database_error();
            exit;
        }

        // Redirects and API responses must wait until their writes have committed.
        ob_start();
        $responseBuffer = ob_get_level();
        register_shutdown_function(static function () use ($database, $original, $session, $responseBuffer) {
            $lastError = error_get_last();
            $fatal = $lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            try {
                if ($fatal) throw new RuntimeException('Request ended before database commit.');
                if (http_response_code() >= 500) {
                    $database->rollBack();
                    return;
                }
                self::save($database, $original);
                $database->commit();

                foreach ([...self::COLLECTIONS, ...self::COUNTERS, ...self::LEGACY_KEYS] as $key) unset($_SESSION[$key]);
                $_SESSION['mypark_workspace'] = self::$workspaceId;
            } catch (Throwable $error) {
                if ($database->inTransaction()) $database->rollBack();
                $_SESSION = $session;
                while (ob_get_level() > $responseBuffer) ob_end_clean();
                if (ob_get_level() === $responseBuffer) ob_clean();
                mypark_database_error();
            }
        });
    }

    private static function sessionWorkspace(array $session): string {
        return $session['mypark_workspace'] ?? (isset($session['demo_users'], $session['demo_lots'])
            ? (($session['vehicle_account_scope'] ?? '') ?: substr(hash('sha256', session_id()), 0, 48))
            : 'shared');
    }

    /** Read committed parking records without taking the workspace write lock. */
    public static function readParkingState(array $session): array {
        $database = mypark_database();
        $workspace = self::sessionWorkspace($session);
        $collections = ['demo_users', 'demo_lots', 'demo_bookings'];
        $user = $session['user'];
        $key = $session['mypark_account_key'] ?? null;

        // A consistent read also covers any multipart records loaded separately.
        $database->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $database->beginTransaction();
        try {
            $data = self::load($database, $workspace, $collections, $key);
            $accounts = $data['demo_users'] ?? [];
            if ($key !== null && ($accounts[$key]['id'] ?? null) !== $user['id']) {
                $accounts = self::load($database, $workspace, ['demo_users'])['demo_users'] ?? [];
            }
            foreach ($accounts as $account) {
                if ($account['id'] === $user['id'] && $account['role'] === $user['role'] && !$account['blocked']
                    && demo_login_matches_password($session, $account['password'])) {
                    $database->commit();
                    return ['role' => $account['role'], 'lots' => array_values($data['demo_lots'] ?? []),
                        'bookings' => $data['demo_bookings'] ?? []];
                }
            }
            throw new RuntimeException('Your account is no longer available. Please sign in again.', 403);
        } finally {
            if ($database->inTransaction()) $database->rollBack();
        }
    }

    private static function load(PDO $database, string $workspace, array $collections = [], ?string $accountKey = null): array {
        $parameters = [$workspace];
        $sql = 'SELECT collection, record_key, payload FROM mypark_records WHERE workspace_id = ?';
        if ($collections) {
            $sql .= ' AND collection IN (' . implode(', ', array_fill(0, count($collections), '?')) . ')';
            array_push($parameters, ...$collections);
        }
        if ($accountKey !== null) {
            $sql .= " AND (collection <> 'demo_users' OR record_key = ?)";
            $parameters[] = $accountKey;
        }
        $query = $database->prepare($sql . ' ORDER BY collection, position');
        $parts = $database->prepare('SELECT bytes FROM mypark_record_parts
            WHERE workspace_id = ? AND collection = ? AND record_key = ? ORDER BY part_number');
        $query->execute($parameters);
        $data = [];
        foreach ($query as $row) {
            $collection = $row['collection'];
            $payload = $row['payload'];
            if ($payload === 'null') {
                $parts->execute([$workspace, $collection, $row['record_key']]);
                $payload = implode('', $parts->fetchAll(PDO::FETCH_COLUMN));
            }
            $value = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (in_array($collection, self::COUNTERS, true)) {
                $data[$collection] = $value;
            } elseif (in_array($collection, self::COLLECTIONS, true)) {
                if ($collection === 'oku_submissions' && isset($value['proof_bytes'])) {
                    $bytes = base64_decode($value['proof_bytes'], true);
                    if ($bytes === false) throw new UnexpectedValueException('Invalid stored OKU proof.');
                    $value['proof_bytes'] = $bytes;
                }
                $data[$collection][$row['record_key']] = $value;
            }
        }
        return $data;
    }

    private static function save(PDO $database, array $original): void {
        if (self::$records === $original) return;
        $write = $database->prepare('INSERT INTO mypark_records (workspace_id, collection, record_key, position, payload)
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE position = VALUES(position), payload = VALUES(payload)');
        $delete = $database->prepare('DELETE FROM mypark_records WHERE workspace_id = ? AND collection = ? AND record_key = ?');
        $writePart = $database->prepare('INSERT INTO mypark_record_parts (workspace_id, collection, record_key, part_number, bytes)
            VALUES (?, ?, ?, ?, ?)');
        $deleteParts = $database->prepare('DELETE FROM mypark_record_parts WHERE workspace_id = ? AND collection = ? AND record_key = ?');

        foreach ([...self::COLLECTIONS, ...self::COUNTERS] as $collection) {
            $previous = $original[$collection] ?? [];
            $current = self::$records[$collection] ?? [];
            if ($previous === $current) continue;
            if (in_array($collection, self::COUNTERS, true)) {
                $previous = isset($original[$collection]) ? ['value' => $previous] : [];
                $current = isset(self::$records[$collection]) ? ['value' => $current] : [];
            }

            $previousOrder = array_flip(array_keys($previous));
            foreach (array_keys(array_diff_key($previous, $current)) as $key) {
                $delete->execute([self::$workspaceId, $collection, (string)$key]);
            }
            $position = 0;
            foreach ($current as $key => $value) {
                if (($previousOrder[$key] ?? null) !== $position || $previous[$key] !== $value) {
                    if ($collection === 'oku_submissions' && isset($value['proof_bytes'])) {
                        $value['proof_bytes'] = base64_encode($value['proof_bytes']);
                    }
                    $payload = json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
                    $record = [self::$workspaceId, $collection, (string)$key];
                    $split = strlen($payload) > MYPARK_DATABASE_PART_BYTES;
                    $write->execute([...$record, $position, $split ? 'null' : $payload]);
                    $deleteParts->execute($record);
                    if ($split) {
                        foreach (str_split($payload, MYPARK_DATABASE_PART_BYTES) as $part => $bytes) {
                            $writePart->execute([...$record, $part, $bytes]);
                        }
                    }
                }
                $position++;
            }
        }
    }
}
