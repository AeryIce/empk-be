<?php

declare(strict_types=1);

const BASE = '127.0.0.1:8080';
const FIXTURE_DATABASE = '/tmp/empk-recovery-ci.sqlite';
const GUARD_BODY = '{"message":"Directory temporarily unavailable."}';

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function request(string $method, string $path, array $headers = []): array
{
    $socket = @stream_socket_client('tcp://'.BASE, timeout: 1);
    if ($socket === false) {
        throw new RuntimeException('Container HTTP listener unavailable.');
    }

    try {
        stream_set_timeout($socket, 5);
        $fields = ['Host: '.BASE, 'Connection: close', ...$headers];
        fwrite($socket, "$method $path HTTP/1.1\r\n".implode("\r\n", $fields)."\r\n\r\n");
        $raw = stream_get_contents($socket);
    } finally {
        fclose($socket);
    }

    $parts = explode("\r\n\r\n", (string) $raw, 2);
    $lines = explode("\r\n", $parts[0]);
    check(count($parts) === 2 && preg_match('/^HTTP\/1\.[01] (\d{3}) /', $lines[0], $status) === 1, 'Invalid container HTTP response.');

    $received = [];
    foreach (array_slice($lines, 1) as $line) {
        [$name, $value] = explode(':', $line, 2);
        $received[strtolower($name)][] = trim($value);
    }

    return [(int) $status[1], $received, $parts[1]];
}

function assertGuarded(string $method, string $path, array $headers = []): void
{
    [$status, $received, $body] = request($method, $path, $headers);
    check($status === 503, "$method $path must return 503, got $status.");
    check(($received['cache-control'] ?? []) === ['no-store, private'], "$method $path must return no-store, private.");
    check(! isset($received['etag']) && ! isset($received['last-modified']), "$method $path must not send validators.");
    check(! isset($received['set-cookie']), "$method $path must not initialize a session.");
    check($body === ($method === 'HEAD' ? '' : GUARD_BODY), "$method $path returned an unexpected body.");
}

check(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 3, 'Smoke must use PHP 8.3.');
check(getenv('APP_ENV') === 'testing' && getenv('DB_CONNECTION') === 'sqlite'
    && getenv('DB_DATABASE') === FIXTURE_DATABASE && getenv('DB_URL') === ''
    && getenv('RAILWAY_ENVIRONMENT_ID') === false && ! file_exists(FIXTURE_DATABASE),
    'Expected a fresh container with disposable SQLite and no Railway context.');

for ($attempt = 0; ; $attempt++) {
    try {
        [$status] = request('GET', '/up');
        check($status === 200, 'Container /up is unhealthy.');
        break;
    } catch (RuntimeException $error) {
        if ($attempt === 19) {
            throw $error;
        }
        usleep(250_000);
    }
}

$conditional = [
    'If-None-Match: *',
    'If-Modified-Since: Fri, 01 Jan 2038 00:00:00 GMT',
];
$cases = [
    ['GET', '/api/schools'],
    ['HEAD', '/api/schools/legacy-school', $conditional],
    ['GET', '/api/schools/unknown?cachebust=1', $conditional],
    ['GET', '/api/foundations/'],
    ['HEAD', '/api/foundations', $conditional],
    ['GET', '/api/foundations/legacy-lpk', $conditional],
    ['GET', '/api/foundations/unknown'],
    ['POST', '/api/schools'],
    ['OPTIONS', '/api/foundations'],
    ['GET', '//api//Schools/'],
    ['GET', '/api/%2573chools/legacy-school'],
    ['GET', '/index.php/api/foundations/legacy-lpk'],
    ['GET', '/other/%2e%2e/api/schools'],
    ['GET', '/api/foundations/%2e%2e'],
];
foreach ($cases as $case) {
    // No database exists yet: the router must return before Laravel boots.
    assertGuarded(...$case);
}
check(! file_exists(FIXTURE_DATABASE), 'Directory request created the SQLite fixture.');

$db = new PDO('sqlite:'.FIXTURE_DATABASE);
$db->exec("
    CREATE TABLE news_posts (id INTEGER PRIMARY KEY, slug TEXT, title TEXT, status TEXT, published_at TEXT);
    INSERT INTO news_posts VALUES (1, 'fixture-news', 'Container smoke fixture', 'published', '2024-01-01 00:00:00');
");
$db = null;

$routes = [
    ['/healthz', 200, 'ok'],
    ['/admin/login', 200, 'wire:submit="authenticate"'],
    ['/admin/activate', 200, 'name="activation_code"'],
    ['/api/berita?next=/api/schools', 200, '"slug":"fixture-news"'],
    ['/api/berita/fixture-news', 200, '"slug":"fixture-news"'],
    ['/favicon.ico', 200, null],
    ['/api/schools-guide', 404, null],
];
foreach ($routes as [$path, $expected, $marker]) {
    [$status, , $body] = request('GET', $path);
    check($status === $expected, "$path must return $expected, got $status.");
    check($marker === null || str_contains($body, $marker), "$path returned an unexpected body.");
}

[$browserStatus, $browserHeaders] = request('GET', '/admin', ['Accept: text/html']);
check($browserStatus === 302 && str_ends_with($browserHeaders['location'][0] ?? '', '/admin/login'), 'The browser must be redirected to Filament login.');
[$jsonStatus] = request('GET', '/admin', ['Accept: application/json']);
check($jsonStatus === 401, 'An unauthenticated JSON request to Filament must return 401.');

printf("PASS: %d directory responses and %d unaffected route checks, including /up.\n", count($cases), count($routes) + 3);
