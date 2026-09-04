<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

$store = \SleekDB\SleekDB::store('speedlogs', __DIR__ . '/', [
    'auto_cache' => false,
    'timeout' => 120
]);

$logs = $store
    ->orderBy('desc', 'created')
    ->limit(MAX_LOG_COUNT)
    ->fetch();

// 关键修正：确保 SleekDB 在无数据时不返回 null，避免 JSON 结构破损引起 parsererror
if (!is_array($logs)) {
    $logs = [];
}

foreach ($logs as &$log) {
    $log['country'] = $log['country'] ?? '';
    $log['region']  = $log['region'] ?? '';
    $log['city']    = $log['city'] ?? '';

    // 区域显示经纬度数据
    $log['area'] = !empty($log['lat_lon']) ? $log['lat_lon'] : ($log['area'] ?? '');
    $log['isp']  = $log['isp'] ?? '';
}

$data = [
    'code'  => 0,
    'msg'   => '',
    'count' => count($logs),
    'data'  => $logs,
];

echo json_encode($data, JSON_UNESCAPED_UNICODE);