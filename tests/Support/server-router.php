<?php

declare(strict_types=1);

// Router for PHP's built-in web server, used by CurlTransportTest.

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = \is_string($uri) ? (string) parse_url($uri, PHP_URL_PATH) : '/';

if ($path === '/redirect') {
    header('Location: https://elsewhere.example/', true, 302);
    echo '{"moved":true}';

    return true;
}
if ($path === '/slow') {
    usleep(2_000_000);
    echo '{}';

    return true;
}
if ($path === '/gzip') {
    header('Content-Type: application/json');
    header('Content-Encoding: gzip');
    echo gzencode('{"compressed":true}');

    return true;
}

http_response_code($path === '/teapot' ? 418 : 200);
header('Content-Type: application/json; charset=utf-8');
header('X-Repeated: one');
header('X-Repeated: two', false);
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'path' => $path,
    'query' => $_SERVER['QUERY_STRING'] ?? null,
    'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
    'body' => file_get_contents('php://input'),
]);

return true;
