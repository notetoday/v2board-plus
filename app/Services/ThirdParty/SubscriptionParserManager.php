<?php

namespace App\Services\ThirdParty;

use App\Services\ThirdParty\Parsers\Base64UriListParser;
use App\Services\ThirdParty\Parsers\ClashYamlParser;
use App\Services\ThirdParty\Parsers\SubscriptionParserInterface;
use App\Services\ThirdParty\Parsers\UriListParser;

class SubscriptionParserManager
{
    /**
     * @var SubscriptionParserInterface[]
     */
    private array $parsers;

    public function __construct(?array $parsers = null)
    {
        $this->parsers = $parsers ?? [
            new ClashYamlParser(),
            new Base64UriListParser(),
            new UriListParser(),
        ];
    }

    /**
     * Parse subscription content into an array of TemporaryNode.
     *
     * @return TemporaryNode[]
     */
    public function parse(string $content, ?string $contentType = null): array
    {
        foreach ($this->parsers as $parser) {
            try {
                if (!$parser->supports($content, $contentType)) {
                    continue;
                }
                $nodes = $parser->parse($content);
                if (!empty($nodes)) {
                    return $nodes;
                }
            } catch (\Throwable $e) {
                // Isolate parser failures: an unsupported or malformed format
                // must not break the sync pipeline.
                continue;
            }
        }
        return [];
    }
}
