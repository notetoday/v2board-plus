<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class Hysteria2Parser extends AbstractUriParser
{
    public static function scheme(): string
    {
        return 'hysteria2';
    }

    public static function parse(string $uri): ?TemporaryNode
    {
        if (strpos($uri, 'hysteria2://') !== 0 && strpos($uri, 'hy2://') !== 0) {
            return null;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }
        $password = self::decodeUserInfo($parts['user'] ?? '');
        $settings = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $settings);
        }
        $settings['credential'] = $password;
        if (isset($settings['obfs-password']) && !isset($settings['obfs_password'])) {
            $settings['obfs_password'] = $settings['obfs-password'];
        }
        if (isset($settings['mport']) && !isset($settings['ports'])) {
            $settings['ports'] = $settings['mport'];
        }
        $name = self::parseName($parts['fragment'] ?? '', $parts['host']);
        return new TemporaryNode('hysteria2', $name, $parts['host'], (int)$parts['port'], $settings, []);
    }
}
