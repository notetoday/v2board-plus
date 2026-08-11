<?php

namespace App\Console\Commands;

use App\Models\ThirdPartySubscription;
use App\Services\ThirdParty\ThirdPartySubscriptionService;
use Illuminate\Console\Command;

class ThirdPartySync extends Command
{
    protected $signature = 'third-party:sync {--source= : Sync a specific subscription source by id} {--force : Bypass the update interval check}';

    protected $description = 'Synchronize third-party subscription sources into the cache';

    public function handle(ThirdPartySubscriptionService $service): int
    {
        if ($this->option('source')) {
            $source = ThirdPartySubscription::find((int)$this->option('source'));
            if (!$source) {
                $this->error("Subscription source [{$this->option('source')}] not found");
                return self::FAILURE;
            }
            $result = $service->sync($source);
            $this->line('third_party_subscription source_id=' . $source->id . ' success=' . ($result['success'] ? 'true' : 'false') . ' node_count=' . $result['node_count']);
            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $results = $service->syncAll((bool)$this->option('force'));
        $success = 0;
        $failed = 0;
        foreach ($results as $sourceId => $result) {
            $this->line('third_party_subscription source_id=' . $sourceId . ' success=' . ($result['success'] ? 'true' : 'false') . ' node_count=' . $result['node_count']);
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }
        $this->line("third_party_subscription sync finished success={$success} failed={$failed}");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
