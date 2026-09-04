<?php

error_reporting(0);
Header("Content-Type: application/json; charset=utf-8");

$ip     = getIp();
$isIpv6 = (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
$source = strtolower(trim($_GET['source'] ?? 'auto'));

if (isPrivateIp($ip) || $source === 'local') {
    // 局域网/内网，或点击测速时强制本地：不请求外网 API，用离线库渲染基础版
    $response = getLocalDbInfo($ip, $isIpv6);
} else {
    // 公网 IP：优先请求 IP.SB 多字段精细数据
    $response = getIpsbInfo($ip, $isIpv6);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * 取得用于判断/展示的客户端 IP。
 *
 * 以外层对端 REMOTE_ADDR 为权威：
 *  - 对端为公网（访客直连、未经过可信反代）：直接使用 REMOTE_ADDR，忽略客户端可自行伪造的
 *    X-Forwarded-For / HTTP_CLIENT_IP，避免公网访客伪装成内网从而跳过 ip.sb；
 *  - 对端为空或私网（nginx 反代 / 局域网）：取 X-Forwarded-For 逗号链中最右一个可解析的 IP
 *    作为真实访客（单层反代以 $proxy_add_x_forwarded_for 追加时，最右即访客）。
 */
function getIp()
{
    $peer = trim($_SERVER['REMOTE_ADDR'] ?? '');

    // 对端是合法且公网的 IP → 直接作为客户端
    if ($peer !== '' && filter_var($peer, FILTER_VALIDATE_IP) && !isPrivateIp($peer)) {
        return $peer;
    }

    // 否则尝试从转发头取真实访客 IP
    $forwarded = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $forwarded = $_SERVER['HTTP_CLIENT_IP'];
    }

    if ($forwarded !== '') {
        foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $candidate) {
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    return $peer !== '' ? $peer : '127.0.0.1';
}

/**
 * 判断 IP 是否为局域网/内网地址（覆盖 IPv4 + IPv6）。
 * 无法解析的输入一律按内网处理：宁可不外呼，也绝不外呼。
 */
function isPrivateIp($ip)
{
    $bin = @inet_pton(trim((string) $ip));
    if ($bin === false) {
        return true;
    }

    if (strlen($bin) === 4) {
        $b0 = ord($bin[0]);
        $b1 = ord($bin[1]);
        if ($b0 === 127 || $b0 === 10 || $b0 === 0) return true;              // loopback / 10/8 / 0/8
        if ($b0 === 172 && $b1 >= 16 && $b1 <= 31) return true;               // 172.16/12
        if ($b0 === 192 && $b1 === 168) return true;                          // 192.168/16
        if ($b0 === 169 && $b1 === 254) return true;                          // 169.254/16 链路本地
        if ($b0 === 100 && ($b1 & 0xC0) === 0x40) return true;                // 100.64/10 CGNAT
        if ($b0 >= 224 && $b0 <= 239) return true;                            // 224/4 组播
        return false;
    }

    if (strlen($bin) === 16) {
        if (substr($bin, 0, 16) === str_repeat("\x00", 16)) return true;                  // ::
        if ($bin[15] === "\x01" && substr($bin, 0, 15) === str_repeat("\x00", 15)) return true; // ::1
        // IPv4 映射地址 ::ffff:a.b.c.d → 递归判断内嵌 IPv4
        if (substr($bin, 0, 10) === str_repeat("\x00", 10) && $bin[10] === "\xff" && $bin[11] === "\xff") {
            return isPrivateIp(inet_ntop(substr($bin, 12)));
        }
        if ($bin[0] === "\xfe" && (ord($bin[1]) & 0xC0) === 0x80) return true; // fe80::/10 链路本地
        if ((ord($bin[0]) & 0xFE) === 0xFC) return true;                       // fc00::/7 ULA
        if ($bin[0] === "\xff") return true;                                   // ff00::/8 组播
        return false;
    }

    return true;
}

/**
 * 请求 IP.SB 多字段精细数据。
 * 仅当 curl 无错 + HTTP 200 + JSON 可解析 + 含 ip 字段时才视为成功；
 * 否则返回 source=unavailable（不返回伪造数据）。
 */
function getIpsbInfo($ip, $isIpv6)
{
    $url = "https://api.ip.sb/geoip/" . urlencode($ip);
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);        // 总超时 3s
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 连接超时 2s，不可达主机快速失败
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $data = curl_exec($ch);
    $errno    = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($data, true);

    if ($errno === 0 && $httpCode === 200 && is_array($json) && isset($json['ip'])) {
        // 计算 UTC 偏移格式，如 offset: 32400 -> UTC+9
        $offsetHours = isset($json['offset']) ? ($json['offset'] / 3600) : 0;
        $utcStr = $offsetHours >= 0 ? "UTC+{$offsetHours}" : "UTC{$offsetHours}";

        return [
            'source'           => 'ip.sb',
            'is_ipv6'          => $isIpv6,
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
            'lat_lon'          => (isset($json['latitude']) && isset($json['longitude'])) ? "{$json['longitude']}, {$json['latitude']}" : '-',
        ];
    }

    return [
        'source'  => 'unavailable',
        'is_ipv6' => $isIpv6,
        'ip'      => $ip,
    ];
}

/**
 * 本地离线库（qqwry.ipdb，仅 IPv4）基础版。
 * 输入为 IPv6、库缺失、查找失败或结果为空时返回空地理字段，
 * 绝不伪造“中国/未知”。
 */
function getLocalDbInfo($ip, $isIpv6)
{
    $dbPath = __DIR__ . '/qqwry.ipdb';
    $rawIspInfo = null;

    if (!$isIpv6 && file_exists($dbPath) && file_exists(__DIR__ . '/Reader.php')) {
        require_once __DIR__ . '/Reader.php';
        try {
            $reader = new \ipip\db\Reader($dbPath);
            $rawIspInfo = $reader->find($ip, 'CN');
        } catch (\Throwable $e) {
            $rawIspInfo = null;
        }
    }

    return [
        'source'  => 'local',
        'is_ipv6' => $isIpv6,
        'ip'      => $ip,
        'country' => isset($rawIspInfo[0]) && $rawIspInfo[0] !== '' ? $rawIspInfo[0] : '',
        'region'  => $rawIspInfo[1] ?? '',
        'city'    => $rawIspInfo[2] ?? '',
        'area'    => $rawIspInfo[3] ?? '',
        'isp'     => $rawIspInfo[5] ?? ($rawIspInfo[4] ?? ''),
    ];
}
