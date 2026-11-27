<?php
// Use the shared Dashboard handler for JSON requests.
const MYPARK_JSON_REQUEST = true;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['background'] ?? '') === '1') {
    define('MYPARK_DASHBOARD_BACKGROUND', true);
}
require dirname(__DIR__) . '/admin/dashboard.php';
