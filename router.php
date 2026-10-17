<?php
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path === '/' || $path === '') {
    header('Location: frontend/pages/index.html');
    exit;
}

$file = __DIR__ . $path;
if (is_file($file)) {
    return false; // let php -S serve it directly
}

http_response_code(404);