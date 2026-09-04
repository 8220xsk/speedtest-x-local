<?php

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

function maskLastSegment($ip) {
    if (empty($ip)) return "Unknown";

    // IPv4 处理
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) == 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.*';
        }
    }

    // IPv6 处理 - 只保留前三段
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $segments = explode(':', $ip);
        if (count($segments) >= 3) {
            $maskedSegments = array_slice($segments, 0, 3);
            return implode(':', $maskedSegments) . ':*';
        }
    }

    return "Unknown";
}

$rawIp = !empty($_POST['ip']) ? filter_var($_POST['ip'], FILTER_DEFAULT) : $_SERVER['REMOTE_ADDR'];

$reportData = [
    "key"     => sha1(!empty($_POST['key']) ? $_POST['key'] : microtime(true) . rand(1000, 9999)),
    "ip"      => maskLastSegment($rawIp),
    "isp"     => isset($_POST['isp']) ? $_POST['isp'] : '',
    "addr"    => isset($_POST['addr']) ? $_POST['addr'] : '',
    "lat_lon" => isset($_POST['lat_lon']) ? $_POST['lat_lon'] : '',
    "dspeed"  => isset($_POST['dspeed']) ? (double)$_POST['dspeed'] : 0,
    "uspeed"  => isset($_POST['uspeed']) ? (double)$_POST['uspeed'] : 0,
    "ping"    => isset($_POST['ping']) ? (double)$_POST['ping'] : 0,
    "jitter"  => isset($_POST['jitter']) ? (double)$_POST['jitter'] : 0,
    "created" => date('Y-m-d H:i:s', time()),
];

if (!empty($reportData['addr'])) {
    $parts = explode(' ', $reportData['addr']);
    if (count($parts) >= 4) {
        $reportData['country'] = $parts[0];
        $reportData['region']  = $parts[1];
        $reportData['city']    = $parts[2];
        $reportData['area']    = $parts[3];
    } else if (count($parts) >= 3) {
        $reportData['country'] = $parts[0];
        $reportData['region']  = $parts[1];
        $reportData['city']    = $parts[2];
    } else if (count($parts) >= 2) {
        $reportData['country'] = $parts[0];
        $reportData['region']  = $parts[1];
    }
}

try {
    // 收到请求时（代表测试完全结束），直接新建一条测速日志
    $store = \SleekDB\SleekDB::store('speedlogs', __DIR__ . '/', ['auto_cache' => false, 'timeout' => 120]);
    $results = $store->insert($reportData);

    // 限制保存最大条数，超出部分删除最旧的一条
    $maxCount = defined('MAX_LOG_COUNT') ? (int)MAX_LOG_COUNT : 100;
    if ($results['_id'] > $maxCount) {
        $store->where('_id', '=', $results['_id'] - $maxCount)->delete();
    }

    echo "1";
} catch (Exception $e) {
    // 记录错误到文件以便调试
    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
    echo "0";
}