<?php

// Finto servizio push per i test: registra le richieste e risponde come Apple/Google.
//   /ok/...   → 201 Created
//   /gone/... → 410 Gone (iscrizione scaduta)
//   /error/...→ 500
$log = getenv('FAKE_PUSH_LOG') ?: sys_get_temp_dir().'/fake-push.log';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents($log, json_encode([
    'path' => $path,
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
    'encoding' => $_SERVER['HTTP_CONTENT_ENCODING'] ?? '',
    'ttl' => $_SERVER['HTTP_TTL'] ?? '',
    'bytes' => strlen((string) file_get_contents('php://input')),
])."\n", FILE_APPEND);

http_response_code(match (true) {
    str_starts_with($path, '/gone') => 410,
    str_starts_with($path, '/error') => 500,
    default => 201,
});
