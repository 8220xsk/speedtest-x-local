<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

$ip = getIp();
$rawIspInfo = getIspInfo($ip);

sendResponse($ip, $rawIspInfo);

function getIp()
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    return trim($ip);
}

function getIspInfo($ip)
{
    $dbPath = __DIR__ . '/qqwry.ipdb';
    if (file_exists($dbPath)) {
        require_once './Reader.php';
        try {
            $reader = new \ipip\db\Reader($dbPath);
            // metowolf 打包的 qqwry.ipdb 直接使用 find($ip) 访问数组元素
            $addr = $reader->find($ip);

            if (is_array($addr)) {
                return [
                    'country'      => $addr[0] ?? '中国',
                    'region'       => $addr[1] ?? '',
                    'city'         => $addr[2] ?? '',
                    'area'         => $addr[3] ?? '',
                    'organization' => $addr[4] ?? '',
                    'isp'          => !empty($addr[5]) ? $addr[5] : ($addr[4] ?? '未知')
                ];
            }
        } catch (Exception $e) {
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