<?php
require_once __DIR__ . '/db.php';

$db        = new UmbraDB();
$storedKey = $db->get('ingest_key');
$clientKey = trim($_POST['name'] ?? '');

if (empty($storedKey)) {
    http_response_code(403);
    exit('No ingest key configured');
}

if (!hash_equals($storedKey, $clientKey)) {
    error_log(sprintf(
        '[Umbra] Rejected RTMP ingest from %s — bad key',
        $_POST['addr'] ?? 'unknown'
    ));
    http_response_code(403);
    exit('Invalid ingest key');
}

http_response_code(200);
exit('OK');
