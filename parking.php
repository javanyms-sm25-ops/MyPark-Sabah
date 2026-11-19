<?php

// Keep old links and form submissions working after the filename change.
$query = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
header('Location: parking-details.php' . ($query === '' ? '' : '?' . $query), true, 308);
exit;
