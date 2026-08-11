<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class ShadowsocksParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'ss';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'ss://') !== 0) {
            return null;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $method = (string)($query['method'] ?? '');
        $password = '';

        if (isset($parts['user']) && $parts['user'] !== '') {
            $decoded = self::base64Decode($parts['user']);
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                [$method, $password] = explode(':', $decoded, 2);
            } elseif ($decoded !== false) {
                $password = $decoded;
            }
        }
        if ($method === '' || $password === '') {
            return null;
        }

        $settings = [
            'credential' => $password,
            'method' => $method,
        ];
        if (isset($query['plugin'])) {
            $settings['plugin'] = (string)$query['plugin'];
            $plugin = (string)$query['plugin'];
            $opts = [];
            if (strpos($plugin, 'obfs-local') === 0 || strpos($plugin, 'simple-obfs') === 0) {
                $segments = explode(';', $plugin);
                array_shift($segments);
                foreach ($segments as $seg) {
                    if (strpos($seg, '=') !== false) {
                        [$k, $v] = explode('=', $seg, 2);
                        $opts[$k] = $v;
                    }
                }
                $settings['obfs'] = $opts['obfs'] ?? '';
                $settings['obfs_host'] = $opts['obfs-host'] ?? $opts['obfs_host'] ?? '';
                $settings['obfs_path'] = $opts['path'] ?? $opts['obfs-uri'] ?? '';
            }
        }
        if (isset($query['obfs'])) {
            $settings['obfs'] = (string)$query['obfs'];
        }
        if (isset($query['obfs-host'])) {
            $settings['obfs_host'] = (string)$query['obfs-host'];
        }
        if (isset($query['path'])) {
            $settings['obfs_path'] = (string)$query['path'];
        }

        return self::node('shadowsocks', $parts, $password, $settings);
    }

    public static function base64Decode(string $value)
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            $decoded = base64_decode($value, true);
        }
        return $decoded;
    }
}
