<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

$store = \SleekDB\SleekDB::store('speedlogs', './', [
    'auto_cache' => false,
    'timeout' => 120
]);

$logs = $store
    ->orderBy('desc', 'created')
    ->limit(MAX_LOG_COUNT)
    ->fetch();

// 确保在无数据时 $logs 为空数组，防止 SleekDB 返回 false/null 导致报错
if (!is_array($logs)) {
    $logs = [];
}

foreach ($logs as &$log) {
    $log['country'] = $log['country'] ?? '';
    $log['region']  = $log['region'] ?? '';
    $log['city']    = $log['city'] ?? '';
    $log['area']    = $log['area'] ?? '';
    $log['isp']     = $log['isp'] ?? '';
}

// Layui 严格要求的表格数据格式
$data = [
    'code'  => 0,
    'msg'   => '',
    'count' => count($logs),
    'data'  => $logs,
];

echo json_encode($data, JSON_UNESCAPED_UNICODE);