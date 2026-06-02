<?php
session_start();
if (empty($_SESSION['authenticated'])) { http_response_code(401); exit; }

header('Content-Type: application/json');

$xml = @file_get_contents('http://127.0.0.1:8080/stat');
if (!$xml) {
    echo json_encode(['connected' => false, 'bitrate_kbps' => 0]);
    exit;
}

libxml_use_internal_errors(true);
$doc = simplexml_load_string($xml);
if (!$doc) {
    echo json_encode(['connected' => false, 'bitrate_kbps' => 0]);
    exit;
}

$connected   = false;
$bitrateKbps = 0;

foreach ($doc->server->application as $app) {
    if ((string)$app->name !== 'live') continue;
    foreach ($app->live->stream as $stream) {
        if ((int)$stream->nclients > 0) {
            $connected   = true;
            $bitrateKbps = (int)((int)$stream->bw_in / 1000);
        }
    }
}

echo json_encode([
    'connected'    => $connected,
    'bitrate_kbps' => $bitrateKbps,
]);
