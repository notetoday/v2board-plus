<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

abstract class AbstractUriParser
{
    abstract public static function scheme(): string;

    public static function supports(string $content, ?string $contentType = null): bool
    {
        return strpos($content, static::scheme() . '://') !== false;
    }

    protected static function decodeUserInfo(string $userinfo): string
    {
        return rawurldecode($userinfo);
    }

    protected static function parseName(string $fragment, string $fallback): string
    {
        $name = $fragment !== '' ? rawurldecode($fragment) : '';
        if ($name === '' || $name === null) {
            $name = $fallback;
        }
        $name = trim($name);
        return $name !== '' ? $name : $fallback;
    }

    /**
     * Build a TemporaryNode from a parsed url array.
     */
    protected static function node(string $type, array $parts, string $credential, array $settings = [], array $metadata = []): ?TemporaryNode
    {
        $host = isset($parts['host']) ? trim($parts['host'], '[]') : null;
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        $name = static::parseName($parts['fragment'] ?? '', $host ?? $type);
        if (!$host || !$port) {
            return null;
        }
        $settings['credential'] = $credential;
        return new TemporaryNode($type, $name, $host, $port, $settings, $metadata);
    }
}
