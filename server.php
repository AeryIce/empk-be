<?php
// Router untuk PHP built-in server agar file statis (css/js/png/svg/woff/woff2) ke-serve langsung.
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = __DIR__ . '/public' . $uri;

if ($uri !== '/' && file_exists($path)) {
    return false; // serve file statis apa adanya
}

require_once __DIR__ . '/public/index.php';
