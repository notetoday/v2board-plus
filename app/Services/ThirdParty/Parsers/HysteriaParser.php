<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class HysteriaParser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'hysteria';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'hysteria://') !== 0) {
            return null;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }
        $settings = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $settings);
        }
        $settings['credential'] = (string)($settings['auth'] ?? '');
        $name = self::parseName($parts['fragment'] ?? '', $parts['host']);
        return new TemporaryNode('hysteria', $name, $parts['host'], (int)$parts['port'], $settings, []);
    }
}
