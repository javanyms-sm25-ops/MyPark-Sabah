<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/dashboard-data.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: sandbox; default-src 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

try {
    $reviewer = ops_actor();
    oku_require_reviewer($reviewer);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        throw new RuntimeException('Open proof from the OKU review queue.', 405);
    }
    ops_valid_csrf($_POST);
    $id = $_POST['submission_id'] ?? null;
    if (!is_string($id)) throw new RuntimeException('OKU submission not found.', 404);

    $row = oku_pending_review($reviewer, $id);
    $mime = $row['proof_mime'] ?? '';
    $proof = $row['proof_bytes'] ?? null;
    if (!isset(OKU_PROOF_TYPES[$mime]) || !is_string($proof) || $proof === '') {
        throw new RuntimeException('OKU proof is unavailable.', 404);
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="oku-parking-proof.' . OKU_PROOF_TYPES[$mime] . '"');
    header('Content-Length: ' . strlen($proof));
    echo $proof;
} catch (RuntimeException $error) {
    http_response_code($error->getCode());
    header('Content-Type: text/plain; charset=utf-8');
    echo t($error->getMessage());
}
