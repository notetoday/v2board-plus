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
        return self::node('trojan', $parts, $password, $settings);
    }
}
