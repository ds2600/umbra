<?php
session_start();
if (empty($_SESSION['authenticated'])) { http_response_code(401); exit; }
header('Content-Type: application/json');
require_once 'db.php';

$db  = new UmbraDB();
$cfg = $db->getAllConfig();

echo json_encode([
    'keys' => [
        'youtube'  => $cfg['key_youtube']  ?? '',
        'facebook' => $cfg['key_facebook'] ?? '',
        'tiktok'   => $cfg['key_tiktok']   ?? '',
    ],
    'enabled' => [
        'youtube'  => (bool)($cfg['enabled_youtube']  ?? 1),
        'facebook' => (bool)($cfg['enabled_facebook'] ?? 1),
        'tiktok'   => (bool)($cfg['enabled_tiktok']   ?? 1),
    ],
    'urls' => [
        'youtube'  => $cfg['url_youtube']  ?? 'rtmp://a.rtmp.youtube.com/live2',
        'facebook' => $cfg['url_facebook'] ?? 'rtmp://127.0.0.1:19350/rtmp',
        'tiktok'   => $cfg['url_tiktok']   ?? 'rtmp://127.0.0.1:19351/game',
    ],
    'stunnel' => [
        'facebook' => $cfg['stunnel_facebook'] ?? 'live-api-s.facebook.com:443',
        'tiktok'   => $cfg['stunnel_tiktok']   ?? 'push-rtmp-f5-tt01.tiktokcdn-us.com:443',
    ],
    'restream'   => (bool)($cfg['restream']   ?? 1),
    'ingest_key' => $cfg['ingest_key'] ?? '',
]);
