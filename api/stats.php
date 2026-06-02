<?php
session_start();
if (empty($_SESSION['authenticated'])) { http_response_code(401); exit; }

header('Content-Type: application/json');

function getCpuUsage(): float {
    $s1 = parseCpuLine();
    usleep(200000);
    $s2 = parseCpuLine();
    $totalDiff = array_sum($s2) - array_sum($s1);
    $idleDiff  = $s2[3] - $s1[3];
    if ($totalDiff === 0) return 0.0;
    return (($totalDiff - $idleDiff) / $totalDiff) * 100;
}

function parseCpuLine(): array {
    $line = '';
    $fh = fopen('/proc/stat', 'r');
    if ($fh) { $line = fgets($fh); fclose($fh); }
    $parts = preg_split('/\s+/', trim($line));
    array_shift($parts);
    return array_map('intval', $parts);
}

function getMemory(): array {
    $info = [];
    $fh = fopen('/proc/meminfo', 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) $info[$m[1]] = (int)$m[2];
            if (isset($info['MemTotal'], $info['MemAvailable'])) break;
        }
        fclose($fh);
    }
    $total = $info['MemTotal'] ?? 1;
    $used  = $total - ($info['MemAvailable'] ?? 0);
    return ['mb' => round($used / 1024, 1), 'pct' => round(($used / $total) * 100, 1)];
}

function getNetStats(): array {
    $cacheFile = '/tmp/umbra_net_cache';
    $now  = microtime(true);
    $data = getNetRaw();
    $prev = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : null;
    file_put_contents($cacheFile, json_encode(['time' => $now, 'data' => $data]));
    if (!$prev) return ['in' => 0, 'out' => 0];
    $dt = $now - $prev['time'];
    if ($dt < 0.01) return ['in' => 0, 'out' => 0];
    return [
        'in'  => max(0, round(($data['in']  - $prev['data']['in'])  / $dt * 8 / 1_000_000, 2)),
        'out' => max(0, round(($data['out'] - $prev['data']['out']) / $dt * 8 / 1_000_000, 2)),
    ];
}

function getNetRaw(): array {
    $in = $out = 0;
    $fh = fopen('/proc/net/dev', 'r');
    if ($fh) {
        fgets($fh); fgets($fh);
        while (($line = fgets($fh)) !== false) {
            $parts = preg_split('/\s+/', trim($line));
            $iface = rtrim($parts[0], ':');
            if ($iface === 'lo') continue;
            $in  += (int)($parts[1] ?? 0);
            $out += (int)($parts[9] ?? 0);
        }
        fclose($fh);
    }
    return ['in' => $in, 'out' => $out];
}

$cpu = getCpuUsage();
$mem = getMemory();
$net = getNetStats();

echo json_encode([
    'cpu'          => round($cpu, 1),
    'mem_mb'       => $mem['mb'],
    'mem_pct'      => $mem['pct'],
    'net_in_mbps'  => $net['in'],
    'net_out_mbps' => $net['out'],
]);
