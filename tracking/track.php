<?php
// Lightweight visit tracker. Receives a JSON beacon from index.html and
// appends one line per event to visits.jsonl (blocked from web access
// by .htaccess). Reads Cloudflare headers for real IP and country.

$file = __DIR__ . '/visits.jsonl';

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 4096) {
    http_response_code(413);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

// keep only expected scalar fields from the client payload
$keep = ['ev','vid','ts','page','ref','sc','vp','dpr','lang','langs','tz',
         'plat','mob','touch','mem','cores','net','ui','dur','to'];
$clean = [];
foreach ($keep as $k) {
    if (isset($data[$k]) && is_scalar($data[$k])) {
        $clean[$k] = mb_substr((string)$data[$k], 0, 300);
    }
}

$rec = [
    'ts' => gmdate('c'),
    'ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
    'cc' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '',
    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 400),
    'al' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 120),
    'd'  => $clean,
];

file_put_contents($file, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

header('Access-Control-Allow-Origin: *');
http_response_code(204);
