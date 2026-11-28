<?php

// Contain the legacy public directory before Laravel or the static file handler runs.
$requestTarget = $_SERVER['REQUEST_URI'] ?? '/';
$directoryPath = preg_match('~^https?://~i', $requestTarget)
    ? (string) parse_url($requestTarget, PHP_URL_PATH)
    : substr($requestTarget, 0, strcspn($requestTarget, '?#'));

$directoryPattern = '~^/(?:index\.php/)?api/(?:schools|foundations)(?:$|[/?#;])~iD';
for ($layer = 0; $layer <= 3; $layer++) {
    // Check both the path the application receives and its dot-normalized form.
    // Pass the original target through for unrelated routes.
    $pathWithSeparators = preg_replace('~/+~', '/', str_replace('\\', '/', $directoryPath));
    $segments = [];
    foreach (explode('/', $pathWithSeparators) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    $normalized = '/'.implode('/', $segments);
    if (preg_match($directoryPattern, $pathWithSeparators) || preg_match($directoryPattern, $normalized)) {
        http_response_code(503);
        header('Cache-Control: no-store, private');
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo '{"message":"Directory temporarily unavailable."}';
        }

        return true;
    }

    $decoded = rawurldecode($directoryPath);
    if ($decoded === $directoryPath) {
        break;
    }

    $directoryPath = $decoded;
}

// Preserve the legacy static-file and Laravel routing behavior.
$uri = urldecode((string) parse_url($requestTarget, PHP_URL_PATH));
$path = __DIR__.'/public'.$uri;

// Pass files to PHP's static server. Send directories such as /admin to Laravel.
if ($uri !== '/' && is_file($path)) {
    return false;
}

require __DIR__.'/public/index.php';
