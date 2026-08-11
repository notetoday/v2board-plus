<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class Base64UriListParser implements SubscriptionParserInterface
{
    private UriListParser $uriListParser;

    public function __construct(?UriListParser $uriListParser = null)
    {
        $this->uriListParser = $uriListParser ?? new UriListParser();
    }

    public function supports(string $content, ?string $contentType = null): bool
    {
        $decoded = $this->decode($content);
        if ($decoded === false) {
            return false;
        }
        return $this->uriListParser->supports($decoded);
    }

    public function parse(string $content): array
    {
        $decoded = $this->decode($content);
        if ($decoded === false) {
            return [];
        }
        return $this->uriListParser->parse($decoded);
    }

    private function decode(string $content)
    {
        $compact = preg_replace('/\s+/', '', $content);
        if ($compact === null || $compact === '') {
            return false;
        }
        $decoded = base64_decode(strtr($compact, '-_', '+/'), true);
        if ($decoded === false) {
            $decoded = base64_decode($compact, true);
        }
        if ($decoded === false || $decoded === '') {
            return false;
        }
        return $decoded;
    }
}
