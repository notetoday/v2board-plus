<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class TrojanParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'trojan';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'trojan://') !== 0) {
            return null;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }
        $password = self::decodeUserInfo($parts['user'] ?? '');
        if ($password === '') {
            return null;
        }
        $settings = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $settings);
        }
        // Trojan URI uses `type` for the transport layer (tcp/ws/grpc).
        // Normalize it to `network` so the converter and V2Board generators
        // can consume it, keeping the raw value intact.
        if (isset($settings['type']) && !isset($settings['network'])) {
            $settings['network'] = $settings['type'];
        }
        // trojan-go uses `peer` as the TLS SNI (same role as `sni`).
        if (isset($settings['peer']) && !isset($settings['sni'])) {
            $settings['sni'] = $settings['peer'];
        }
        // trojan-go websocket obfs is expressed via the obfs-local plugin:
        // plugin=obfs-local;obfs=websocket;obfs-host=<host>;obfs-uri=<path>
        self::applyObfsLocalPlugin($settings);
        return self::node('trojan', $parts, $password, $settings);
    }

    private static function applyObfsLocalPlugin(array &$settings): void
    {
        $plugin = (string)($settings['plugin'] ?? '');
        if ($plugin === '' || (strpos($plugin, 'obfs-local') !== 0 && strpos($plugin, 'simple-obfs') !== 0)) {
            return;
        }
        $opts = [];
        $segments = explode(';', $plugin);
        array_shift($segments);
        foreach ($segments as $segment) {
            if (strpos($segment, '=') !== false) {
                [$key, $value] = explode('=', $segment, 2);
                $opts[$key] = $value;
            }
        }
        if (strtolower((string)($opts['obfs'] ?? '')) === 'websocket') {
            if (!isset($settings['network'])) {
                $settings['network'] = 'ws';
            }
            $settings['path'] = (string)($opts['obfs-uri'] ?? ($opts['path'] ?? ''));
            $host = (string)($opts['obfs-host'] ?? '');
            if ($host === '') {
                $host = (string)($settings['host'] ?? ($settings['sni'] ?? ''));
            }
            $settings['host'] = $host;
        }
    }
}
