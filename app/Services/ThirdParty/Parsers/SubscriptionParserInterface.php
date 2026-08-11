<?php

namespace App\Services\ThirdParty\Parsers;

/**
 * Parses raw third-party subscription content into temporary nodes.
 *
 * Parsers must never interact with the database.
 */
interface SubscriptionParserInterface
{
    public function supports(string $content, ?string $contentType = null): bool;

    /**
     * @return array|TemporaryNode[]
     */
    public function parse(string $content): array;
}
