<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

$ip = getIp();

// 判断是否为 IPv6 地址
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $response = getIpsbInfo($ip);
} else {
    $response = getLocalDbInfo($ip);
}

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

function getIpsbInfo($ip)
{
    $url = "https://api.ip.sb/geoip/" . urlencode($ip);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $data = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($data, true);

    if ($json && isset($json['ip'])) {
        // 计算 UTC 偏移格式，如 offset: 32400 -> UTC+9
        $offsetHours = isset($json['offset']) ? ($json['offset'] / 3600) : 0;
        $utcStr = $offsetHours >= 0 ? "UTC+{$offsetHours}" : "UTC{$offsetHours}";

        return [
            'is_ipv6'          => true,
            'ip'               => $ip,
            'isp'              => $json['isp'] ?? '-',
            'organization'     => $json['organization'] ?? '-',
            'asn'              => isset($json['asn']) ? 'AS' . $json['asn'] : '-',
            'asn_organization' => $json['asn_organization'] ?? '-',
            'continent_code'   => $json['continent_code'] ?? '-',
            'country'          => $json['country'] ?? '-',
            'country_code'     => $json['country_code'] ?? '',
            'region'           => $json['region'] ?? '-',
            'region_code'      => $json['region_code'] ?? '',
            'city'             => $json['city'] ?? '-',
            'postal_code'      => $json['postal_code'] ?? '-',
            'timezone'         => isset($json['timezone']) ? "{$json['timezone']} ({$utcStr})" : '-',
            'lat_lon'          => (isset($json['latitude']) && isset($json['longitude'])) ? "{$json['latitude']}, {$json['longitude']}" : '-'
        ];
    }

    return getFallbackResponse($ip, true);
}

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
        'is_ipv6'          => false,
        'ip'               => $ip,
        'country'          => !empty($rawIspInfo[0]) ? $rawIspInfo[0] : '中国',
        'region'           => $rawIspInfo[1] ?? '',
        'city'             => $rawIspInfo[2] ?? '',
        'area'             => $rawIspInfo[3] ?? '',
        'isp'              => $rawIspInfo[5] ?? ($rawIspInfo[4] ?? '未知')
    ];
}

function getFallbackResponse($ip, $isIpv6 = false)
{
    return [
        'is_ipv6' => $isIpv6,
        'ip'      => $ip,
        'isp'     => '未知',
        'country' => '中国'
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