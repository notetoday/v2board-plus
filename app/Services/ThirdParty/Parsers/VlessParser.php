<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class VlessParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'vless';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'vless://') !== 0) {
            return null;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }
        $userinfo = $parts['user'] ?? '';
        $uuid = self::decodeUserInfo($userinfo);
        if ($uuid === '') {
            return null;
        }
        $settings = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $settings);
        }
        // VLESS URI uses `type` for the transport layer (tcp/ws/grpc/kcp/
        // httpupgrade/xhttp). Normalize it to `network` so the converter and
        // V2Board generators can consume it, keeping the raw value intact.
        if (isset($settings['type']) && !isset($settings['network'])) {
            $settings['network'] = $settings['type'];
        }
        return self::node('vless', $parts, $uuid, $settings);
    }
}
