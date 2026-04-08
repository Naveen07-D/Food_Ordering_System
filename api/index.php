<?php
// index.php (in the same folder as api.php)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if (is_file($file)) {
    return false; // serve the requested resource as-is
}
require __DIR__ . '/api.php';
exit();
?>
