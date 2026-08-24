<?php

namespace App\Protocols;

use App\Utils\Helper;

class Surfboard
{
    public $flag = 'surfboard';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;

        $appName = config('v2board.app_name', 'V2Board');
        header("content-disposition:attachment;filename*=UTF-8''".rawurlencode($appName).".conf");

        $proxies = '';
        $proxyGroup = '';

        foreach ($servers as $item) {
            if (($item['type'] ?? null) === 'v2node' && isset($item['protocol'])) {
                $item['type'] = $item['protocol'];
            }
            if ($item['type'] === 'shadowsocks'
                && in_array($item['cipher'], [
                    '2022-blake3-aes-128-gcm',
                    '2022-blake3-aes-256-gcm',
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305',
                    'xchacha20-ietf-poly1305',
                    'aes-128-cfb',
                    'aes-192-cfb',
                    'aes-256-cfb',
                    'aes-128-ctr',
                    'aes-192-ctr',
                    'aes-256-ctr',
                    'rc4',
                    'rc4-md5',
                    'bf-cfb',
                    'camellia-128-cfb',
                    'camellia-192-cfb',
                    'camellia-256-cfb',
                    'salsa20',
                    'chacha20',
                    'chacha20-ietf'
                ])
            ) {
                // [Proxy]
                $proxies .= self::buildShadowsocks(Helper::resolveServerCredential($user['uuid'], $item), $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'vmess') {
                // [Proxy]
                $vmessCredential = Helper::resolveServerCredential($user['uuid'], $item);
                if (Helper::isValidUuid((string)$vmessCredential)) {
                    $proxies .= self::buildVmess($vmessCredential, $item);
                    // [Proxy Group]
                    $proxyGroup .= $item['name'] . ', ';
                }
            }
            if ($item['type'] === 'trojan') {
                // [Proxy]
                $proxies .= self::buildTrojan(Helper::resolveServerCredential($user['uuid'], $item), $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'tuic') {
                // [Proxy]
                $proxies .= self::buildTuic(Helper::resolveServerCredential($user['uuid'], $item), $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }
            if (($item['type'] === 'hysteria' && ($item['version'] ?? 1) === 2) || $item['type'] === 'hysteria2') { //surfboard只支持hysteria2
                // [Proxy]
                $proxies .= self::buildHysteria(Helper::resolveServerCredential($user['uuid'], $item), $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'anytls') {
                // [Proxy]
                $proxies .= self::buildAnyTLS(Helper::resolveServerCredential($user['uuid'], $item), $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }
        }

        $defaultConfig = base_path() . '/resources/rules/default.surfboard.conf';
        $customConfig = base_path() . '/resources/rules/custom.surfboard.conf';
        if (\File::exists($customConfig)) {
            $config = file_get_contents("$customConfig");
        } else {
            $config = file_get_contents("$defaultConfig");
        }

        // Subscription link
        $subsURL = Helper::getSubscribeUrl($user['token']);
        $subsDomain = $_SERVER['HTTP_HOST'];

        $config = str_replace('$subs_link', $subsURL, $config);
        $config = str_replace('$subs_domain', $subsDomain, $config);
        $config = str_replace('$proxies', $proxies, $config);
        $config = str_replace('$proxy_group', rtrim($proxyGroup, ', '), $config);

        $upload = round($user['u'] / (1024*1024*1024), 2);
        $download = round($user['d'] / (1024*1024*1024), 2);
        $useTraffic = $upload + $download;
        $totalTraffic = round($user['transfer_enable'] / (1024*1024*1024), 2);
        $expireDate = $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']);
        $subscribeInfo = "title={$appName}订阅信息, content=上传流量：{$upload}GB\\n下载流量：{$download}GB\\n剩余流量：{$useTraffic}GB\\n套餐流量：{$totalTraffic}GB\\n到期时间：{$expireDate}";
        $config = str_replace('$subscribe_info', $subscribeInfo, $config);

        return $config;
    }


    public static function buildShadowsocks($password, $server)
    {
        if (!isset($server['sub_uuid']) && $server['cipher'] === '2022-blake3-aes-128-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 16);
            $userKey = Helper::uuidToBase64($password, 16);
            $password = "{$serverKey}:{$userKey}";
        } elseif (!isset($server['sub_uuid']) && $server['cipher'] === '2022-blake3-aes-256-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 32);
            $userKey = Helper::uuidToBase64($password, 32);
            $password = "{$serverKey}:{$userKey}";
        }
        $config = [
            "{$server['name']}=ss",
            "{$server['host']}",
            "{$server['port']}",
            "encrypt-method={$server['cipher']}",
            "password={$password}",
            'tfo=true',
            'udp-relay=true'
        ];
        if (isset($server['obfs']) && in_array($server['obfs'], ['http', 'tls'])) {
            $config[] = "obfs={$server['obfs']}";
            if (isset($server['obfs-host']) && !empty($server['obfs-host'])) {
                $config[] = "obfs-host={$server['obfs-host']}";
            }
            if ($server['obfs'] === 'http' && isset($server['obfs-path']) && !empty($server['obfs-path'])) {
                $config[] = "obfs-uri={$server['obfs-path']}";
            }
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVmess($uuid, $server)
    {
        $config = [
            "{$server['name']}=vmess",
            "{$server['host']}",
            "{$server['port']}",
            "username={$uuid}",
            "vmess-aead=true",
            'tfo=true',
            'udp-relay=true'
        ];

        if ($server['tls']) {
            array_push($config, 'tls=true');
            $tlsSettings = $server['tlsSettings'] ?? ($server['tls_settings'] ?? []);
            if ($tlsSettings) {
                $allowInsecure = $tlsSettings['allowInsecure'] ?? ($tlsSettings['allow_insecure'] ?? 0);
                $serverName = $tlsSettings['serverName'] ?? ($tlsSettings['server_name'] ?? '');
                if (!empty($allowInsecure))
                    array_push($config, 'skip-cert-verify=' . ($allowInsecure ? 'true' : 'false'));
                if (!empty($serverName))
                    array_push($config, "sni={$serverName}");
            }
        }
        if ($server['network'] === 'ws') {
            array_push($config, 'ws=true');
            $wsSettings = $server['networkSettings'] ?? ($server['network_settings'] ?? []);
            if ($wsSettings) {
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    array_push($config, "ws-path={$wsSettings['path']}");
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    array_push($config, "ws-headers=Host:{$wsSettings['headers']['Host']}");
                if (isset($wsSettings['security'])) {
                    $encryptMethod = match ($wsSettings['security']) {
                        'aes-128-gcm' => 'aes-128-gcm',
                        'chacha20-poly1305' => 'chacha20-ietf-poly1305',
                        default => null,
                    };
                    if ($encryptMethod !== null)
                        array_push($config, "encrypt-method={$encryptMethod}");
                }
            }
        }

        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildTrojan($password, $server)
    {
        $config = [
            "{$server['name']}=trojan",
            "{$server['host']}",
            "{$server['port']}",
            "password={$password}",
            $server['server_name'] ? "sni={$server['server_name']}" : "",
            'tfo=true',
            'udp-relay=true'
        ];
        if (!empty($server['allow_insecure'])) {
            array_push($config, $server['allow_insecure'] ? 'skip-cert-verify=true' : 'skip-cert-verify=false');
        }
        if(isset($server['network']) && $server['network'] === "ws") {
            array_push($config, "ws=true");
            if(isset($server['network_settings']['path']) && !empty($server['network_settings']['path'])) {
                array_push($config, "ws-path={$server['network_settings']['path']}");
            }
            if(isset($server['network_settings']['headers']['Host']) && !empty($server['network_settings']['headers']['Host'])) {
                array_push($config, "ws-headers=Host:{$server['network_settings']['headers']['Host']}");
            }
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildTuic($password, $server)
    {
        $config = [
            "{$server['name']}=tuic-v5",
            "{$server['host']}",
            "{$server['port']}",
            "uuid={$password}",
            "password={$password}",
            "alpn=h3",
            'udp-relay=true'
        ];
        $tlsSettings = $server['tls_settings'] ?? [];
        $sni = $server['server_name'] ?? ($tlsSettings['server_name'] ?? '');
        if ($sni) {
            $config[] = "sni={$sni}";
        }
        $insecure = $server['insecure'] ?? ($tlsSettings['allow_insecure'] ?? 0);
        $config[] = 'skip-cert-verify=' . ($insecure ? 'true' : 'false');

        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildHysteria($password, $server)
    {
        $parts = explode(",",$server['port']);
        $firstPart = $parts[0];
        if (strpos($firstPart, '-') !== false) {
            $range = explode('-', $firstPart);
            $firstPort = $range[0];
        } else {
            $firstPort = $firstPart;
        }

        $config = [
            "{$server['name']}=hysteria2",
            "{$server['host']}",
            "{$firstPort}",
            "password={$password}",
            !empty($server['up_mbps']) ? "download-bandwidth={$server['up_mbps']}" : "",
            $server['server_name'] ? "sni={$server['server_name']}" : "",
            'udp-relay=true'
        ];
        $tlsSettings = $server['tls_settings'] ?? [];
        $insecure = $server['insecure'] ?? ($tlsSettings['allow_insecure'] ?? 0);
        if (!empty($insecure)) {
            array_push($config, $insecure ? 'skip-cert-verify=true' : 'skip-cert-verify=false');
        }
        if (count($parts) !== 1 || strpos($firstPart, '-') !== false) {
            $hopping = str_replace(',', ';', (string)$server['port']);
            $config[] = "port-hopping=\"{$hopping}\"";
        }
        if (!empty($server['obfs']) && !empty($server['obfs_password'])) {
            $config[] = 'salamander-password=' . $server['obfs_password'];
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildAnyTLS($password, $server)
    {
        $tlsSettings = $server['tls_settings'] ?? [];
        $allowInsecure = ($server['insecure'] ?? ($tlsSettings['allow_insecure'] ?? 0)) == 1 ? 'true' : 'false';
        $sni = $server['server_name'] ?? ($tlsSettings['server_name'] ?? '');

        $config = [
            "{$server['name']}=anytls",
            "{$server['host']}",
            "{$server['port']}",
            "{$password}",
            "skip-cert-verify={$allowInsecure}",
        ];

        if ($sni) {
            $config[] = "sni={$sni}";
        }
        $config[] = "reuse=false";

        $uri = implode(', ', $config);
        $uri .= "\r\n";
        return $uri;
    }
}
