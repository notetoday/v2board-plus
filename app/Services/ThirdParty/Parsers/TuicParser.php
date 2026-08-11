<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class TuicParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'tuic';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'tuic://') !== 0) {
            return null;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }
        $userinfo = self::decodeUserInfo($parts['user'] ?? '');
        if (isset($parts['pass']) && $parts['pass'] !== '') {
            $userinfo .= ':' . rawurldecode($parts['pass']);
        }
        $credential = $userinfo;
        if (strpos($userinfo, ':') !== false) {
            $segments = explode(':', $userinfo, 2);
            $credential = $segments[1] !== '' ? $segments[1] : $segments[0];
        }
        $settings = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $settings);
        }
        $settings['credential'] = $credential;
        $name = self::parseName($parts['fragment'] ?? '', $parts['host']);
        return new TemporaryNode('tuic', $name, $parts['host'], (int)$parts['port'], $settings, []);
    }
}
