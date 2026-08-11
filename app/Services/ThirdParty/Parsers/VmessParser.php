<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class VmessParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'vmess';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'vmess://') !== 0) {
            return null;
        }
        $payload = substr($uri, strlen('vmess://'));
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            $json = base64_decode($payload, true);
        }
        if ($json === false) {
            return null;
        }
        $config = json_decode($json, true);
        if (!is_array($config)) {
            return null;
        }
        $host = $config['add'] ?? ($config['address'] ?? null);
        $port = isset($config['port']) ? (int)$config['port'] : null;
        $name = $config['ps'] ?? ($host ?? 'vmess');
        if (!$host || !$port) {
            return null;
        }
        $settings = [
            'credential' => (string)($config['id'] ?? ''),
            'network' => (string)($config['net'] ?? 'tcp'),
            'tls' => (string)($config['tls'] ?? ''),
            'sni' => (string)($config['sni'] ?? ''),
            'host' => (string)($config['host'] ?? ''),
            'path' => (string)($config['path'] ?? ''),
            'type' => (string)($config['type'] ?? 'none'),
            'fp' => (string)($config['fp'] ?? ''),
            'allowInsecure' => (int)($config['allowInsecure'] ?? 0),
            'security' => (string)($config['scy'] ?? ''),
            'serviceName' => (string)($config['serviceName'] ?? ''),
            'seed' => (string)($config['seed'] ?? ''),
            'mode' => (string)($config['mode'] ?? ''),
            'alpn' => (string)($config['alpn'] ?? ''),
            'skip-cert-verify' => (string)($config['skip-cert-verify'] ?? ''),
        ];
        return new TemporaryNode('vmess', $name, $host, $port, $settings, []);
    }
}
