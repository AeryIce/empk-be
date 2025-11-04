<?php
// Router untuk PHP built-in server.
// HANYA serve file statis; semua selain itu lempar ke Laravel.

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = __DIR__ . '/public' . $uri;

// penting: cek is_file (bukan file_exists) agar direktori seperti /admin
// tidak ditangani server statis (yang berujung 403), tapi ke Laravel.
if ($uri !== '/' && is_file($path)) {
    return false;
}

require __DIR__ . '/public/index.php';
