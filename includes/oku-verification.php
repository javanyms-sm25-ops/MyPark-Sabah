<?php

const OKU_STATUS_LABELS = [
    'not_submitted' => 'Not Submitted',
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];
const OKU_PROOF_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const OKU_MAX_PROOF_BYTES = 2000000;
const OKU_APPROVAL_REQUIRED = 'Approved OKU parking verification is required. Check your status or submit proof in Profile.';

function oku_current_submission(int $memberId): ?array {
    $id = $_SESSION['oku_latest'][$memberId] ?? '';
    return $_SESSION['oku_submissions'][$id] ?? null;
}

function oku_is_approved(int $memberId): bool {
    return (oku_current_submission($memberId)['status'] ?? null) === 'approved';
}

function oku_can_review(array $account): bool {
    return !$account['blocked'] && ($account['role'] === 'superadmin'
        || ($account['role'] === 'admin' && ($account['can_review_oku'] ?? false) === true));
}

function oku_require_reviewer(array $account): void {
    if (!oku_can_review($account)) {
        throw new RuntimeException('You do not have permission to review OKU submissions.', 403);
    }
}

function oku_require_private_session(): void {
    $store = realpath(session_save_path());
    $project = dirname(__DIR__);
    $webRoot = realpath(($_SERVER['DOCUMENT_ROOT'] ?? '') ?: $project);
    if (session_status() !== PHP_SESSION_ACTIVE || session_module_name() !== 'files' || !$store || !$webRoot) {
        throw new RuntimeException('Private proof storage is unavailable. Please try again later.', 503);
    }

    $normalize = static function (string $path): string {
        $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    };
    foreach ([$project, $webRoot] as $root) {
        if (str_starts_with($normalize($store), $normalize($root))) {
            throw new RuntimeException('Private proof storage is unavailable. Please try again later.', 503);
        }
    }
}

function oku_submit(array $member, array $file): void {
    if ($member['role'] !== 'user') {
        throw new RuntimeException('Only members can submit OKU parking proof.', 403);
    }
    oku_require_private_session();

    $current = oku_current_submission($member['id']);
    if ($current && $current['status'] !== 'rejected') {
        throw new RuntimeException('You can submit new proof after a rejection.', 409);
    }

    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose a file for your OKU parking proof.', 422);
    }
    if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('OKU parking proof must be 2 MB or smaller.', 422);
    }
    $path = $file['tmp_name'] ?? null;
    if ($uploadError !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        throw new RuntimeException('Proof upload failed. Choose the file again.', 422);
    }
    $size = @filesize($path);
    if ($size === false || $size === 0) {
        throw new RuntimeException('Proof upload failed. Choose the file again.', 422);
    }
    if ($size > OKU_MAX_PROOF_BYTES) {
        throw new RuntimeException('OKU parking proof must be 2 MB or smaller.', 422);
    }
    $mime = @mime_content_type($path);
    $image = @getimagesize($path);
    if (!isset(OKU_PROOF_TYPES[$mime]) || ($image['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Upload a valid JPG, PNG or WebP image for your proof.', 422);
    }
    $bytes = @file_get_contents($path, false, null, 0, OKU_MAX_PROOF_BYTES);
    if ($bytes === false || strlen($bytes) !== $size) {
        throw new RuntimeException('Proof upload failed. Choose the file again.', 422);
    }

    $id = bin2hex(random_bytes(16));
    $_SESSION['oku_submissions'][$id] = [
        'id' => $id,
        'member_id' => $member['id'],
        'submitted_at' => time(),
        'status' => 'pending',
        'proof_mime' => $mime,
        'proof_bytes' => $bytes,
    ];
    $_SESSION['oku_latest'][$member['id']] = $id;
}

function oku_queue_filter(mixed $value): string {
    return in_array($value, ['pending', 'approved', 'rejected', 'all'], true) ? $value : 'pending';
}

function oku_review_rows(array $reviewer, string $filter): array {
    oku_require_reviewer($reviewer);
    $accounts = array_column($_SESSION['demo_users'], null, 'id');
    $rows = [];
    foreach ($_SESSION['oku_submissions'] ?? [] as $submission) {
        $member = $accounts[$submission['member_id']] ?? null;
        if (($member['role'] ?? null) !== 'user' || ($filter !== 'all' && $submission['status'] !== $filter)) continue;
        $rows[] = [
            'id' => $submission['id'],
            'name' => $member['name'],
            'submitted_at' => $submission['submitted_at'],
            'status' => $submission['status'],
            'reviewed_at' => $submission['reviewed_at'] ?? null,
            'reviewer_name' => $submission['reviewer_name'] ?? null,
        ];
    }
    return array_reverse($rows);
}

function oku_pending_review(array $reviewer, string $id): array {
    oku_require_reviewer($reviewer);
    oku_require_private_session();
    $row = preg_match('/\A[a-f0-9]{32}\z/', $id) ? ($_SESSION['oku_submissions'][$id] ?? null) : null;
    $accounts = array_column($_SESSION['demo_users'], null, 'id');
    if (!$row || ($accounts[$row['member_id']]['role'] ?? null) !== 'user') {
        throw new RuntimeException('OKU submission not found.', 404);
    }
    if ($row['status'] !== 'pending' || ($_SESSION['oku_latest'][$row['member_id']] ?? '') !== $id) {
        throw new RuntimeException('This submission has already been reviewed. Refresh the queue.', 409);
    }
    return $row;
}

function oku_decide(array $reviewer, string $id, string $decision, string $reason): string {
    // This read and write happen while PHP holds the same exclusive file-session lock.
    $row = oku_pending_review($reviewer, $id);
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new RuntimeException('Choose Approve or Reject.', 422);
    }
    $reason = $decision === 'rejected' ? trim($reason) : '';
    if ($decision === 'rejected') {
        $length = preg_match_all('/./us', $reason);
        if (!$length || $length > 500) {
            throw new RuntimeException('Enter a rejection reason of 1 to 500 characters.', 422);
        }
    }

    $row['status'] = $decision;
    $row['reviewer_id'] = $reviewer['id'];
    $row['reviewer_name'] = $reviewer['name'];
    $row['reviewed_at'] = time();
    $row['reason'] = $reason;
    unset($row['proof_bytes'], $row['proof_mime']);
    $_SESSION['oku_submissions'][$id] = $row;
    return $decision === 'approved' ? 'OKU submission approved.' : 'OKU submission rejected.';
}

function oku_set_reviewer_access(array $actor, int $adminId, bool $granted): string {
    if ($actor['role'] !== 'superadmin') {
        throw new RuntimeException('Only Super Admin can change OKU review access.', 403);
    }
    foreach ($_SESSION['demo_users'] as &$account) {
        if ($account['id'] === $adminId && $account['role'] === 'admin') {
            $account['can_review_oku'] = $granted;
            return $granted ? 'OKU review access granted.' : 'OKU review access revoked.';
        }
    }
    unset($account);
    throw new RuntimeException('Admin account not found.', 404);
}
