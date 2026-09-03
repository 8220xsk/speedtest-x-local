<?php

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

$store = \SleekDB\SleekDB::store('speedlogs', './',[
    'auto_cache' => false,
    'timeout' => 120
]);

$logs = $store
    ->orderBy( 'desc', 'created' )
    ->limit( MAX_LOG_COUNT )
    ->fetch();

// 为没有新字段的旧记录添加默认值
foreach ($logs as &$log) {
    if (!isset($log['country'])) {
        $log['country'] = '';
    }
    if (!isset($log['region'])) {
        $log['region'] = '';
    }
    if (!isset($log['city'])) {
        $log['city'] = '';
    }
    if (!isset($log['area'])) {
        $log['area'] = '';
    }
}

$data = [
    'code' => 0,
    'data' => $logs,
];

echo json_encode($data);