<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;

class UriListParser implements SubscriptionParserInterface
{
    private const SCHEME_PARSERS = [
        VlessParser::class,
        VmessParser::class,
        TrojanParser::class,
        ShadowsocksParser::class,
        HysteriaParser::class,
        Hysteria2Parser::class,
        TuicParser::class,
        AnytlsParser::class,
    ];

    public function supports(string $content, ?string $contentType = null): bool
    {
        foreach ($this->extractLines($content) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '://') === false) {
                continue;
            }
            if ($this->findParser($line) !== null) {
                return true;
            }
        }
        return false;
    }

    public function parse(string $content): array
    {
        $nodes = [];
        foreach ($this->extractLines($content) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '://') === false) {
                continue;
            }
            $parser = $this->findParser($line);
            if ($parser === null) {
                continue;
            }
            try {
                $node = $parser::parse($line);
                if ($node !== null) {
                    $nodes[] = $node;
                }
            } catch (\Throwable $e) {
                // Isolate broken node lines: a single malformed URI must not
                // prevent the rest of the subscription from being parsed.
                continue;
            }
        }
        return $nodes;
    }

    private function findParser(string $line): ?string
    {
        foreach (self::SCHEME_PARSERS as $parser) {
            if (strpos($line, $parser::scheme() . '://') === 0) {
                return $parser;
            }
        }
        return null;
    }

    private function extractLines(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        return explode("\n", $content);
    }
}
