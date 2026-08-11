<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class AnytlsParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'anytls';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'anytls://') !== 0) {
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
        return self::node('anytls', $parts, $password, $settings);
    }
}
