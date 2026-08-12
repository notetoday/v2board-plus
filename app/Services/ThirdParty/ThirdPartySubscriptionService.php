<?php

namespace App\Services\ThirdParty;

use App\Models\ThirdPartySubscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Manages the third-party subscription data flow.
 *
 * The database only stores subscription source configuration. Parsed nodes
 * exist exclusively in cache/in-memory during a request and are never
 * persisted to any node table.
 */
class ThirdPartySubscriptionService
{
    private const CACHE_PREFIX = 'third_party_subscription_nodes_';

    private SubscriptionFetcher $fetcher;
    private SubscriptionParserManager $parserManager;
    private TemporaryNodeConverter $converter;

    public function __construct(
        ?SubscriptionFetcher $fetcher = null,
        ?SubscriptionParserManager $parserManager = null,
        ?TemporaryNodeConverter $converter = null
    ) {
        $this->fetcher = $fetcher ?? new SubscriptionFetcher();
        $this->parserManager = $parserManager ?? new SubscriptionParserManager();
        $this->converter = $converter ?? new TemporaryNodeConverter();
    }

    public static function cacheKey(int $sourceId): string
    {
        return self::CACHE_PREFIX . $sourceId;
    }

    public function cacheExpiresIn(ThirdPartySubscription $source): int
    {
        return max(60, (int)$source->update_interval) * 60;
    }

    /**
     * Fetch + parse + validate + cache for a single source.
     *
     * On failure the previous cache is intentionally preserved and the error
     * recorded on the source row. The cache is only ever refreshed on success.
     *
     * @return array{success: bool, node_count: int, error: ?string}
     */
    public function sync(ThirdPartySubscription $source): array
    {
        try {
            $content = $this->fetcher->fetch((string)$source->url);
        } catch (SubscriptionFetchException $e) {
            $this->markFailed($source, $e->getMessage());
            return ['success' => false, 'node_count' => 0, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            $this->markFailed($source, 'unexpected error: ' . $e->getMessage());
            return ['success' => false, 'node_count' => 0, 'error' => $e->getMessage()];
        }

        $nodes = $this->parserManager->parse($content);

        if (empty($nodes)) {
            $error = 'parsed subscription contained no usable nodes';
            $this->markFailed($source, $error);
            return ['success' => false, 'node_count' => 0, 'error' => $error];
        }

        $this->storeCache($source, $nodes);

        $source->last_sync_at = time();
        $source->last_error = null;
        $source->save();

        return ['success' => true, 'node_count' => count($nodes), 'error' => null];
    }

    /**
     * Sync every enabled source that is due according to its update_interval.
     *
     * @param bool $force bypass the update interval check
     * @return array<string, array{success: bool, node_count: int, error: ?string}>
     */
    public function syncAll(bool $force = false): array
    {
        $results = [];
        $sources = ThirdPartySubscription::where('enabled', 1)->get();
        foreach ($sources as $source) {
            if (!$force && !$this->isDue($source)) {
                continue;
            }
            try {
                $results[$source->id] = $this->sync($source);
            } catch (\Throwable $e) {
                // A single failing source must never abort the batch.
                $results[$source->id] = ['success' => false, 'node_count' => 0, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public function isDue(ThirdPartySubscription $source): bool
    {
        if (!$source->enabled) {
            return false;
        }
        $interval = max(1, (int)$source->update_interval) * 60;
        if ($source->last_sync_at === null) {
            return true;
        }
        return (time() - (int)$source->last_sync_at) >= $interval;
    }

    /**
     * Read the cached nodes for all enabled sources and convert them into the
     * V2Board server-array representation.
     */
    public function getAvailableServersForUser(User $user): array
    {
        if (!(int)config('v2board.third_party_subscription_enable', 1)) {
            return [];
        }

        $groupConfig = config('v2board.third_party_subscription_groups', '');
        if (!empty($groupConfig)) {
            $groups = array_filter(array_map('intval', explode(',', $groupConfig)));
            if (!empty($groups) && !in_array((int)$user->group_id, $groups, true)) {
                return [];
            }
        }

        $sources = ThirdPartySubscription::where('enabled', 1)->orderBy('sort', 'ASC')->get();

        $servers = [];
        foreach ($sources as $source) {
            $nodes = $this->getCachedNodes($source->id);
            if (empty($nodes)) {
                continue;
            }
            foreach ($nodes as $node) {
                $server = $this->converter->convert($node, (int)$source->id);
                if ($server !== null) {
                    $servers[] = $server;
                }
            }
        }

        return $servers;
    }

    /**
     * @return TemporaryNode[]
     */
    public function getCachedNodes(int $sourceId): array
    {
        $cached = Cache::store('redis')->get(self::cacheKey($sourceId));
        if (!is_array($cached) || empty($cached['parsed_nodes'])) {
            return [];
        }
        $nodes = [];
        foreach ($cached['parsed_nodes'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            try {
                $nodes[] = TemporaryNode::fromArray($item);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $nodes;
    }

    public function getCachedNodeCount(int $sourceId): int
    {
        return count($this->getCachedNodes($sourceId));
    }

    public function getCacheFetchedAt(int $sourceId): ?int
    {
        $cached = Cache::store('redis')->get(self::cacheKey($sourceId));
        if (!is_array($cached)) {
            return null;
        }
        return isset($cached['fetched_at']) ? (int)$cached['fetched_at'] : null;
    }

    public function clearCache(int $sourceId): void
    {
        Cache::store('redis')->forget(self::cacheKey($sourceId));
    }

    private function storeCache(ThirdPartySubscription $source, array $nodes): void
    {
        $payload = [
            'source_id' => (int)$source->id,
            'fetched_at' => time(),
            'expires_at' => time() + $this->cacheExpiresIn($source),
            'parsed_nodes' => array_map(fn(TemporaryNode $node) => $node->toArray(), $nodes),
        ];
        Cache::store('redis')->put(self::cacheKey((int)$source->id), $payload, $this->cacheExpiresIn($source));
    }

    private function markFailed(ThirdPartySubscription $source, string $error): void
    {
        try {
            $source->last_error = mb_substr($error, 0, 500);
            $source->save();
        } catch (\Throwable $e) {
            // Never escalate database write errors during sync.
        }
    }
}
