<?php
// Keep the old admin entry point working for bookmarks and form submissions.
$query = http_build_query($_GET);
header('Location: dashboard.php' . ($query === '' ? '' : '?' . $query), true, 308);
exit;
