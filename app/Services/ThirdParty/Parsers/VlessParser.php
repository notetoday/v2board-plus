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
        return self::node('vless', $parts, $uuid, $settings);
    }
}
