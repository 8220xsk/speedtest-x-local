<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

$ip = getIp();
$rawIspInfo = getIspInfo($ip);

// 精准对接标准版 qqwry.ipdb 的数组索引
$response = [
    'ip'      => $ip,
    'country' => !empty($rawIspInfo[0]) ? $rawIspInfo[0] : '中国',
    'region'  => $rawIspInfo[1] ?? '',
    'city'    => $rawIspInfo[2] ?? '',
    'area'    => $rawIspInfo[3] ?? '',  // district_name 区县
    'isp'     => $rawIspInfo[5] ?? ($rawIspInfo[4] ?? '未知') // isp_domain 位于索引 5
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
    $dbPath = __DIR__ . '/qqwry.ipdb';
    
    if (file_exists($dbPath) && file_exists(__DIR__ . '/Reader.php')) {
        require_once __DIR__ . '/Reader.php';
        try {
            $reader = new \ipip\db\Reader($dbPath);
            // 必须传入语言参数 'CN' 以匹配索引列
            return $reader->find($ip, 'CN');
        } catch (\Throwable $e) {
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