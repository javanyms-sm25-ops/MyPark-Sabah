<?php
require 'includes.php';
require 'operations-data.php';
try {
    $actor = ops_actor();
    if (!in_array($actor['role'], ['admin', 'superadmin'], true)) throw new RuntimeException('Only administrators can export records.', 403);
    foreach ($_GET as $value) if (!is_string($value)) throw new RuntimeException('Invalid export filters.', 400);
    $table = $_GET['table'] ?? 'reservations';
    if (!in_array($table, ['accounts', 'reservations'], true)) throw new RuntimeException('Unknown export table.', 400);
    $snapshot = ops_snapshot($actor);
    $rows = ops_filter_sort($snapshot[$table], $table, $_GET);
} catch (RuntimeException $error) {
    http_response_code($error->getCode() ?: 400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $error->getMessage();
    exit;
}
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
header('Content-Disposition: attachment; filename="mypark-' . $table . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$columns = $table === 'accounts'
    ? ['id' => 'ID', 'name' => 'Name', 'email' => 'Email', 'role_label' => 'Role', 'status' => 'Status']
    : ['id' => 'ID', 'user' => 'Member', 'lot' => 'Location', 'city' => 'City', 'plate' => 'Plate', 'hours' => 'Duration (hours)', 'fee' => 'Fee MYR', 'status' => 'Status', 'created_at' => 'Created'];
fputcsv($out, array_values($columns), ',', '"', '');
foreach ($rows as $row) {
    $values = [];
    foreach ($columns as $key => $label) $values[] = ops_csv_cell($key === 'fee' ? number_format($row[$key], 2, '.', '') : $row[$key]);
    fputcsv($out, $values, ',', '"', '');
}
fclose($out);
