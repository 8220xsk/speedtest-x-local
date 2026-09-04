<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

$ip = getIp();

// 判断是否为 IPv6 地址
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    // IPv6 -> 走 API.IP.SB 在线解析
    $response = getIpsbInfo($ip);
} else {
    // IPv4 -> 走 本地 qqwry.ipdb 离线库
    $response = getLocalDbInfo($ip);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * 获取客户端真实 IP
 */
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

/**
 * 请求 api.ip.sb 获取 IPv6 准确地理及运营商数据
 */
function getIpsbInfo($ip)
{
    $url = "https://api.ip.sb/geoip/" . urlencode($ip);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 100.0; Win64; x64)');
    $data = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($data, true);

    if ($json && isset($json['ip'])) {
        return [
            'ip'      => $ip,
            'country' => $json['country'] ?? '',
            'region'  => $json['region'] ?? '',
            'city'    => $json['city'] ?? '',
            'area'    => '',
            'isp'     => $json['organization'] ?? ($json['isp'] ?? '')
        ];
    }

    // 保底处理
    return [
        'ip'      => $ip,
        'country' => '中国',
        'region'  => '',
        'city'    => '',
        'area'    => '',
        'isp'     => ''
    ];
}

/**
 * 读取本地 qqwry.ipdb 本地数据库（用于 IPv4）
 */
function getLocalDbInfo($ip)
{
    $dbPath = __DIR__ . '/qqwry.ipdb';
    $rawIspInfo = null;

    if (file_exists($dbPath) && file_exists(__DIR__ . '/Reader.php')) {
        require_once __DIR__ . '/Reader.php';
        try {
            $reader = new \ipip\db\Reader($dbPath);
            $rawIspInfo = $reader->find($ip, 'CN');
        } catch (\Throwable $e) {
            $rawIspInfo = null;
        }
    }

    return [
        'ip'      => $ip,
        'country' => !empty($rawIspInfo[0]) ? $rawIspInfo[0] : '中国',
        'region'  => $rawIspInfo[1] ?? '',
        'city'    => $rawIspInfo[2] ?? '',
        'area'    => $rawIspInfo[3] ?? '',
        'isp'     => $rawIspInfo[5] ?? ($rawIspInfo[4] ?? '')
    ];
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