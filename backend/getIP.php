<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

$ip = getIp();
$rawIspInfo = getIspInfo($ip);

$response = [
    'ip'      => $ip,
    'country' => $rawIspInfo[0] ?? '中国',
    'region'  => $rawIspInfo[1] ?? '',
    'city'    => $rawIspInfo[2] ?? '',
    'area'    => $rawIspInfo[3] ?? '',
    'isp'     => $rawIspInfo[4] ?? ($rawIspInfo[5] ?? '未知')
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);

function getIp()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    return trim($ip);
}

function getIspInfo($ip)
{
    // 适配容器内 /backend/qqwry.ipdb 的实际存放路径
    $dbPath = __DIR__ . '/qqwry.ipdb';
    
    if (file_exists($dbPath) && file_exists(__DIR__ . '/Reader.php')) {
        require_once __DIR__ . '/Reader.php';
        try {
            $reader = new \ipip\db\Reader($dbPath);
            // Reader.php 的 find 方法必须传入语言参数 'CN'
            $addr = $reader->find($ip, 'CN');
            return $addr;
        } catch (\Throwable $e) {
            // 捕获所有异常，防止 PHP 直接抛出致命错误导致空白响应
            return null;
        }
    }
    return null;
}

function sendResponse($ip, $rawIspInfo = null)
{
    $processedString = $ip;
    if (is_array($rawIspInfo)) {
        $country = $rawIspInfo['country'] ?? '';
        $region = $rawIspInfo['region'] ?? '';
        $city = $rawIspInfo['city'] ?? '';
        $isp = $rawIspInfo['isp'] ?? '';

        $locationParts = array_filter([$country, $region, $city, $isp]);
        if (!empty($locationParts)) {
            $processedString .= ' - ' . implode(' ', $locationParts);
        }
    }

    $response = [
        'processedString' => $processedString,
        'rawIspInfo' => $rawIspInfo
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}