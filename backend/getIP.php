<?php

error_reporting(0);

Header("Content-Type: application/json; charset=utf-8");

$ip = getIp();
$ipService = getIpService();
$rawIspInfo = getIspInfo($ip, $ipService);
$isp = getIsp($rawIspInfo, $ipService);

sendResponse($ip, $isp, $rawIspInfo);

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

function getIpService()
{
    $ipService = 'ip.sb';
    if (file_exists('../config.php')) {
        include '../config.php';
        if (isset($MAX_LOG_COUNT) && defined('IP_SERVICE')) {
            $ipService = IP_SERVICE;
        }
    }
    return $ipService;
}

function getIspInfo($ip, $ipService)
{
    if ($ipService === 'local') {
        $dbPath = __DIR__ . '/qqwry.ipdb';
        if (file_exists($dbPath)) {
            require_once './Reader.php';
            try {
                $reader = new \ipip\db\Reader($dbPath);
                // 最新指引：直接 find($ip)，不能加 'CN' 参数
                $addr = $reader->find($ip);

                if (is_array($addr)) {
                    return [
                        'country'      => $addr[0] ?? '中国', // country_name
                        'region'       => $addr[1] ?? '',     // region_name (省)
                        'city'         => $addr[2] ?? '',     // city_name (市)
                        'area'         => $addr[3] ?? '',     // district_name (区)
                        'organization' => $addr[4] ?? '',     // owner_domain
                        'isp'          => !empty($addr[5]) ? $addr[5] : ($addr[4] ?? '未知') // isp_domain (运营商)
                    ];
                }
            } catch (Exception $e) {
                return null;
            }
        }
    }

    $url = '';
    if ($ipService == 'ip.sb') {
        $url = 'https://api.ip.sb/geoip/' . $ip;
    } elseif ($ipService == 'ipinfo.io') {
        $url = 'https://ipinfo.io/' . $ip . '/json';
    } else {
        return null;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function getIsp($rawIspInfo, $ipService)
{
    if ($ipService == 'ip.sb') {
        if (
            !is_array($rawIspInfo)
            || !array_key_exists('organization', $rawIspInfo)
            || !is_string($rawIspInfo['organization'])
            || empty($rawIspInfo['organization'])
        ) {
            return 'Unknown';
        }
        return $rawIspInfo['organization'];
    } elseif ($ipService == 'ipinfo.io') {
        if (
            !is_array($rawIspInfo)
            || !array_key_exists('org', $rawIspInfo)
            || !is_string($rawIspInfo['org'])
            || empty($rawIspInfo['org'])
        ) {
            return 'Unknown';
        }
        return preg_replace('/AS\\d+\\s/', '', $rawIspInfo['org']);
    } elseif ($ipService == 'local') {
        return is_array($rawIspInfo) ? ($rawIspInfo['isp'] ?? '未知') : 'Unknown';
    }
    return 'Unknown';
}

function sendResponse($ip, $ipInfo = null, $rawIspInfo = null)
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
    } elseif (is_string($ipInfo) && $ipInfo !== 'Unknown') {
        $processedString .= ' - ' . $ipInfo;
    }

    $response = [
        'processedString' => $processedString,
        'rawIspInfo' => $rawIspInfo
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}