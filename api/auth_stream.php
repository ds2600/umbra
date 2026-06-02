<?php
/**
 * Umbra — RTMP ingest key validator
 *
 * nginx-rtmp calls this via on_publish with POST fields:
 *   name  = the stream key the client provided (what you set in Insta360 / Larix)
 *   app   = the application name (e.g. "live")
 *   addr  = client IP address
 *   tcurl = full RTMP URL
 *
 * Return HTTP 200 to allow, anything else to deny.
 * This endpoint must be reachable from 127.0.0.1 only (enforced by nginx config).
 */

require_once __DIR__ . '/db.php';

$db         = new UmbraDB();
$storedKey  = $db->get('ingest_key');
$clientKey  = trim($_POST['name'] ?? '');

// Empty stored key = no auth configured, deny everything for safety
if (empty($storedKey)) {
    http_response_code(403);
    exit('No ingest key configured');
}

// hash_equals prevents timing attacks
if (!hash_equals($storedKey, $clientKey)) {
    // Log the failed attempt (useful for debugging bad keys in Larix/Insta360)
    error_log(sprintf(
        '[Umbra] Rejected RTMP ingest from %s — bad key (received: %s)',
        $_POST['addr'] ?? 'unknown',
        substr($clientKey, 0, 8) . '…'
    ));
    http_response_code(403);
    exit('Invalid ingest key');
}

// Key matched — allow the stream
http_response_code(200);
exit('OK');
