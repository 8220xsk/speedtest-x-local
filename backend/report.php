<?php

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

function maskLastSegment($ip) {
    if (empty($ip)) return "Unknown";
    $ipaddr = @inet_pton($ip);
    if ($ipaddr === false) return "Unknown"; // 防错：遇到无法解析的 IP 直接返回 Unknown，防止程序崩掉

    if (strlen($ipaddr) == 4) {
        $ipaddr[3] = chr(0);
    } elseif (strlen($ipaddr) == 16) {
        $ipaddr[14] = chr(0);
        $ipaddr[15] = chr(0);
    } else {
        return "Unknown";
    }
    return rtrim(inet_ntop($ipaddr), "0") . "*";
}

$store = \SleekDB\SleekDB::store('speedlogs', './', [
    'auto_cache' => false,
    'timeout'    => 120
]);

// 获取客户端 IP
$rawIp = !empty($_POST['ip']) ? filter_var($_POST['ip'], FILTER_DEFAULT) : $_SERVER['REMOTE_ADDR'];

$reportData = [
    "key"     => sha1(!empty($_POST['key']) ? $_POST['key'] : microtime(true) . rand(1000, 9999)),
    "ip"      => maskLastSegment($rawIp),
    "isp"     => isset($_POST['isp']) ? $_POST['isp'] : '',
    "addr"    => isset($_POST['addr']) ? $_POST['addr'] : '',
    "dspeed"  => isset($_POST['dspeed']) ? (double)$_POST['dspeed'] : 0,
    "uspeed"  => isset($_POST['uspeed']) ? (double)$_POST['uspeed'] : 0,
    "ping"    => isset($_POST['ping']) ? (double)$_POST['ping'] : 0,
    "jitter"  => isset($_POST['jitter']) ? (double)$_POST['jitter'] : 0,
    "created" => date('Y-m-d H:i:s', time()),
];

// 解析地址字段
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

// 常量兼容性处理
$isMultiLog = defined('SAME_IP_MULTI_LOGS') && (SAME_IP_MULTI_LOGS === true || SAME_IP_MULTI_LOGS === 'true' || SAME_IP_MULTI_LOGS === 1);
$maxCount   = defined('MAX_LOG_COUNT') ? (int)MAX_LOG_COUNT : 100;

if ($isMultiLog) {
    // 开启多记录模式：每次测速都新增记录（2.json, 3.json...）
    $results = $store->insert($reportData);
    if ($results['_id'] > $maxCount) {
        $store->where('_id', '=', $results['_id'] - $maxCount)->delete();
    }
} else {
    // 覆盖模式：按 IP 查旧记录更新
    $oldLog = $store->where('ip', '=', $reportData['ip'])->orderBy('desc', '_id')->fetch();
    if (is_array($oldLog) && empty($oldLog)) {
        $results = $store->insert($reportData);
    } else {
        $id = $oldLog[0]['_id'];
        unset($reportData['ip']);
        $store->where('_id', '=', $id)->update($reportData);
    }
}

echo "1";