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
$maskedIp = maskLastSegment($rawIp);

// 位置列回退：新版前端按 country/region/city/area 独立字段上报；
// 旧格式客户端未带独立字段、仅有 addr 时，用空格拆分回退（兼容老浏览器/旧缓存）
$countryProvided = isset($_POST['country']) ? $_POST['country'] : '';
if ($countryProvided === '' && !empty($_POST['addr'])) {
    $parts = explode(' ', $_POST['addr']);
    $_POST['country'] = $parts[0] ?? '';
    $_POST['region']  = $parts[1] ?? '';
    $_POST['city']    = $parts[2] ?? '';
    $_POST['area']    = $parts[3] ?? '';
}

// 只收集本次上报中“非空”的可更新字段：非空才补写，避免把同一条记录里的旧栏目清空
$patch = [];
if ($maskedIp !== '' && $maskedIp !== 'Unknown') {
    $patch['ip'] = $maskedIp;
}
foreach (['isp', 'country', 'region', 'city', 'area', 'addr', 'lat_lon'] as $f) {
    if (isset($_POST[$f]) && $_POST[$f] !== '') {
        $patch[$f] = $_POST[$f];
    }
}
foreach (['dspeed', 'uspeed', 'ping', 'jitter'] as $f) {
    if (isset($_POST[$f]) && $_POST[$f] !== '' && is_numeric($_POST[$f])) {
        $patch[$f] = (double) $_POST[$f];
    }
}

// 每次测速由前端携带唯一 key：第一次上报时新建该 key 的记录（新的 json），
// 之后的每次上报只 update 同一条记录，测速各栏目随进度逐渐补全。
// 旧格式（无 key）客户端退化为每次新建一条，保持向后兼容。
$keyInput = isset($_POST['key']) ? trim((string) $_POST['key']) : '';
$reportKey = sha1($keyInput !== '' ? $keyInput : uniqid('', true));

try {
    $store = \SleekDB\SleekDB::store('speedlogs', __DIR__ . '/', ['auto_cache' => false, 'timeout' => 120]);

    $existing = $store->where('key', '=', $reportKey)->fetch();
    if (is_array($existing) && !empty($existing)) {
        // 已存在该次测速记录：只补填本次上报的非空字段
        if (!empty($patch)) {
            $store->where('key', '=', $reportKey)->update($patch);
        }
    } else {
        // 首次收到该 key：新建一条记录，随后续上报逐渐补全
        $newLog = $patch;
        $newLog['key']     = $reportKey;
        $newLog['created'] = date('Y-m-d H:i:s', time());
        $store->insert($newLog);
    }

    // 只保留最新的 MAX_LOG_COUNT 条记录，超出部分删除最旧的
    $maxCount = defined('MAX_LOG_COUNT') ? (int) MAX_LOG_COUNT : 100;
    $all = $store->orderBy('asc', '_id')->fetch();
    if (is_array($all) && count($all) > $maxCount) {
        $excessCount = count($all) - $maxCount;
        foreach (array_slice($all, 0, $excessCount) as $old) {
            $store->where('_id', '=', $old['_id'])->delete();
        }
    }

    echo "1";
} catch (Exception $e) {
    // 记录错误到文件以便调试
    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
    echo "0";
}
