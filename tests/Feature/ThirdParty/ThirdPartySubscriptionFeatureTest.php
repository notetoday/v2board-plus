<?php

namespace Tests\Feature\ThirdParty;

use App\Models\ThirdPartySubscription;
use App\Models\User;
use App\Services\ServerService;
use App\Services\ThirdParty\SubscriptionFetcher;
use App\Services\ThirdParty\SubscriptionParserManager;
use App\Services\ThirdParty\TemporaryNode;
use App\Services\ThirdParty\TemporaryNodeConverter;
use App\Services\ThirdParty\ThirdPartySubscriptionService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ThirdPartySubscriptionFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        $this->cleanup();
    }

    protected function ensureSchema(): void
    {
        if (!Schema::hasTable('third_party_subscriptions')) {
            Schema::create('third_party_subscriptions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255)->default('');
                $table->text('url');
                $table->unsignedTinyInteger('enabled')->default(1);
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedInteger('update_interval')->default(60);
                $table->integer('last_sync_at')->nullable();
                $table->text('last_error')->nullable();
                $table->integer('created_at')->default(0);
                $table->integer('updated_at')->default(0);
            });
        }
    }

    protected function cleanup(): void
    {
        foreach ([
            'v2_server_vless', 'v2_server_vmess', 'v2_server_trojan',
            'v2_server_shadowsocks', 'v2_server_tuic', 'v2_server_hysteria',
            'v2_server_anytls', 'v2_server_v2node', 'v2_user',
            'third_party_subscriptions',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
        foreach (ThirdPartySubscription::all() as $source) {
            Cache::store('redis')->forget(ThirdPartySubscriptionService::cacheKey((int)$source->id));
        }
        config(['v2board.third_party_subscription_enable' => 1]);
        config(['v2board.third_party_subscription_groups' => '']);
    }

    protected function makeUser(int $groupId = 1): User
    {
        return User::create([
            'uuid' => 'test-uuid-' . uniqid(),
            'token' => 'test-token-' . uniqid(),
            'email' => 'user' . uniqid() . '@example.com',
            'password' => 'x',
            'group_id' => $groupId,
            'transfer_enable' => 1024 * 1024 * 1024,
            'u' => 0,
            'd' => 0,
            'banned' => 0,
            'expired_at' => null,
        ]);
    }

    protected function makeSource(array $attributes = []): ThirdPartySubscription
    {
        return ThirdPartySubscription::create(array_merge([
            'name' => 'Test Source',
            'url' => 'https://example.com/sub',
            'enabled' => 1,
            'sort' => 0,
            'update_interval' => 60,
        ], $attributes));
    }

    protected function fetcher(string $content): SubscriptionFetcher
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'text/plain'], $content),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new Client(['handler' => $stack]);
        return new SubscriptionFetcher($client, function () {
            return [['type' => 'A', 'host' => 'example.com', 'ip' => '93.184.216.34']];
        });
    }

    protected function failingFetcher(): SubscriptionFetcher
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('cURL error 28', new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/sub')),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new Client(['handler' => $stack]);
        return new SubscriptionFetcher($client, function () {
            return [['type' => 'A', 'host' => 'example.com', 'ip' => '93.184.216.34']];
        });
    }

    protected function service(?SubscriptionFetcher $fetcher = null): ThirdPartySubscriptionService
    {
        return new ThirdPartySubscriptionService(
            $fetcher,
            new SubscriptionParserManager(),
            new TemporaryNodeConverter()
        );
    }

    protected function subscribeContent(): string
    {
        return "vless://tp-uuid-1@tp-a.example.com:443?type=tcp&security=tls&sni=tp-a.example.com#TP-A\n"
            . "trojan://tp-pass-2@tp-b.example.com:443#TP-B\n";
    }

    // ---------------------------------------------------------------- tests

    public function testOnlyOwnNodes()
    {
        $user = $this->makeUser();
        DB::table('v2_server_vless')->insert([
            'group_id' => json_encode([1]),
            'name' => 'Own Vless',
            'host' => 'own.example.com',
            'port' => 443,
            'server_port' => 443,
            'tls' => 1,
            'network' => 'tcp',
            'rate' => 1,
            'show' => 1,
            'sort' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(1, $servers);
        $this->assertSame('Own Vless', $servers[0]['name']);
        $this->assertArrayNotHasKey('source_type', $servers[0]);
    }

    public function testOnlyThirdPartyNodes()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $result = $this->service($this->fetcher($this->subscribeContent()))->sync($source);
        $this->assertTrue($result['success']);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(2, $servers);
        foreach ($servers as $server) {
            $this->assertSame('third_party', $server['source_type']);
            $this->assertSame((int)$source->id, $server['source_id']);
            $this->assertTrue($server['is_third_party']);
            $this->assertNotEmpty($server['sub_uuid']);
        }
    }

    public function testOwnAndThirdPartyBothPresent()
    {
        $user = $this->makeUser();
        DB::table('v2_server_vless')->insert([
            'group_id' => json_encode([1]),
            'name' => 'Own Vless',
            'host' => 'own.example.com',
            'port' => 443,
            'server_port' => 443,
            'tls' => 1,
            'network' => 'tcp',
            'rate' => 1,
            'show' => 1,
            'sort' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $source = $this->makeSource();
        $this->service($this->fetcher($this->subscribeContent()))->sync($source);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(3, $servers);
        $names = array_column($servers, 'name');
        $this->assertContains('Own Vless', $names);
        $this->assertContains('TP-A', $names);
        $this->assertContains('TP-B', $names);
    }

    public function testMultipleSources()
    {
        $user = $this->makeUser();
        $sourceA = $this->makeSource(['name' => 'A']);
        $sourceB = $this->makeSource(['name' => 'B', 'url' => 'https://example.com/sub2']);
        $this->service($this->fetcher("vless://u1@a.example.com:443#A1\n"))->sync($sourceA);
        $this->service($this->fetcher("vless://u2@b.example.com:443#B1\nvless://u3@b2.example.com:443#B2\n"))->sync($sourceB);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(3, $servers);
        $names = array_column($servers, 'name');
        $this->assertContains('A1', $names);
        $this->assertContains('B1', $names);
        $this->assertContains('B2', $names);
    }

    public function testOneSourceFailsOthersStillWork()
    {
        $user = $this->makeUser();
        $good = $this->makeSource(['name' => 'good']);
        $bad = $this->makeSource(['name' => 'bad', 'url' => 'https://example.com/bad']);
        $this->service($this->fetcher("vless://u1@a.example.com:443#A1\n"))->sync($good);
        $result = $this->service($this->failingFetcher())->sync($bad);
        $this->assertFalse($result['success']);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(1, $servers);
        $this->assertSame('A1', $servers[0]['name']);

        $this->assertNotNull($bad->refresh()->last_error);
    }

    public function testDisabledSourceExcluded()
    {
        $user = $this->makeUser();
        $enabled = $this->makeSource(['name' => 'enabled']);
        $disabled = $this->makeSource(['name' => 'disabled', 'enabled' => 0]);
        $this->service($this->fetcher("vless://u1@a.example.com:443#A1\n"))->sync($enabled);
        $this->service($this->fetcher("vless://u2@b.example.com:443#B1\n"))->sync($disabled);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(1, $servers);
        $this->assertSame('A1', $servers[0]['name']);
    }

    public function testStaleCacheStillUsedWhenExpired()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $this->service($this->fetcher($this->subscribeContent()))->sync($source);

        $node = new TemporaryNode('vless', 'Stale-Node', 'stale.example.com', 443, ['credential' => 'stale-uuid', 'type' => 'tcp', 'security' => 'none']);
        Cache::store('redis')->put(ThirdPartySubscriptionService::cacheKey((int)$source->id), [
            'source_id' => (int)$source->id,
            'fetched_at' => time() - 7200,
            'expires_at' => time() - 3600,
            'parsed_nodes' => [$node->toArray()],
        ], 3600);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(1, $servers);
        $this->assertSame('Stale-Node', $servers[0]['name']);
    }

    public function testGlobalToggleDisablesThirdParty()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $this->service($this->fetcher($this->subscribeContent()))->sync($source);
        config(['v2board.third_party_subscription_enable' => 0]);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(0, $servers);
    }

    public function testGroupFilter()
    {
        $user = $this->makeUser(1);
        $source = $this->makeSource();
        $this->service($this->fetcher($this->subscribeContent()))->sync($source);
        config(['v2board.third_party_subscription_groups' => '2,3']);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(0, $servers);

        $user2 = $this->makeUser(2);
        $servers = (new ServerService())->getAvailableServers($user2);
        $this->assertCount(2, $servers);
    }

    public function testSyncFailurePreservesOldCache()
    {
        $source = $this->makeSource();
        $service = $this->service($this->fetcher($this->subscribeContent()));
        $result = $service->sync($source);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $service->getCachedNodeCount((int)$source->id));

        $failed = $this->service($this->failingFetcher())->sync($source->refresh());
        $this->assertFalse($failed['success']);

        // Old cache must be preserved.
        $this->assertSame(2, $service->getCachedNodeCount((int)$source->id));
        $this->assertNotNull($source->refresh()->last_error);
    }

    public function testDuplicateNodesAreDeduplicated()
    {
        $source = $this->makeSource();
        $content = "vless://same@a.example.com:443?type=tcp#Dup\n"
            . "vless://same@a.example.com:443?type=tcp#Dup-Renamed\n"
            . "vless://other@b.example.com:443?type=tcp#Real\n";
        $result = $this->service($this->fetcher($content))->sync($source);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['node_count']);
        $this->assertSame(2, $this->service()->getCachedNodeCount((int)$source->id));
    }

    public function testFingerprintVariantsOfSameNodeAreDeduplicated()
    {
        $source = $this->makeSource();
        $content = "vless://same@a.example.com:443?type=ws&security=tls&host=a.example.com&path=%2Fv1&fp=chrome#A\n"
            . "vless://same@a.example.com:443?type=ws&security=tls&host=a.example.com&path=%2Fv1&fp=edge#B\n"
            . "vless://same@a.example.com:443?type=ws&security=tls&host=a.example.com&path=%2Fv1&fp=firefox#C\n";
        $result = $this->service($this->fetcher($content))->sync($source);
        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['node_count']);
    }

    public function testDifferentTransportSettingsAreNotMerged()
    {
        $source = $this->makeSource();
        $content = "vless://same@a.example.com:443?type=ws&security=tls&host=a.example.com&path=%2Fv1#Ws\n"
            . "vless://same@a.example.com:443?type=tcp&security=tls&sni=a.example.com#Tcp\n";
        $result = $this->service($this->fetcher($content))->sync($source);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['node_count']);
    }

    public function testCrossSourceDedupeKeepsMostCompleteNodeVariant()
    {
        $user = $this->makeUser();
        $sourceA = $this->makeSource(['name' => 'A', 'sort' => 1]);
        $sourceB = $this->makeSource(['name' => 'B', 'sort' => 2, 'url' => 'https://example.com/b']);
        $this->service($this->fetcher("vless://same@a.example.com:443?type=ws&security=tls#A-Complete\n"))->sync($sourceA);
        $this->service($this->fetcher("vless://same@a.example.com:443?type=ws&security=tls&host=cdn.example.com&path=%2Fv1&fp=chrome#B-Complete\n"))->sync($sourceB);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(1, $servers);
        $server = $servers[0];
        $this->assertSame('cdn.example.com', $server['network_settings']['headers']['Host']);
        $this->assertSame('/v1', $server['network_settings']['path']);
    }

    public function testDeduplicationAcrossSources()
    {
        $user = $this->makeUser();
        $sourceA = $this->makeSource(['name' => 'A']);
        $sourceB = $this->makeSource(['name' => 'B', 'url' => 'https://example.com/b']);
        $this->service($this->fetcher("vless://same@a.example.com:443#A\n"))->sync($sourceA);
        $this->service($this->fetcher("vless://same@a.example.com:443#B\n"))->sync($sourceB);

        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertCount(1, $servers);
    }

    public function testSyncDoesNotPersistNodesToNodeTables()
    {
        $this->makeSource();
        $source = ThirdPartySubscription::first();
        $countsBefore = $this->nodeTableCounts();
        $result = $this->service($this->fetcher($this->subscribeContent()))->sync($source);
        $this->assertTrue($result['success']);
        $this->assertSame($countsBefore, $this->nodeTableCounts());
    }

    public function testThirdPartyNodesNeverWrittenToDatabase()
    {
        $source = $this->makeSource();
        $service = $this->service($this->fetcher($this->subscribeContent()));
        $service->sync($source);

        $user = $this->makeUser();
        $servers = (new ServerService())->getAvailableServers($user);
        $this->assertNotEmpty($servers);

        $this->assertSame(0, DB::table('v2_server_vless')->count());
        $this->assertSame(0, DB::table('v2_server_vmess')->count());
        $this->assertSame(0, DB::table('v2_server_trojan')->count());
        $this->assertSame(0, DB::table('v2_server_shadowsocks')->count());
        $this->assertSame(0, DB::table('v2_server_tuic')->count());
        $this->assertSame(0, DB::table('v2_server_hysteria')->count());
        $this->assertSame(0, DB::table('v2_server_anytls')->count());
        $this->assertSame(0, DB::table('v2_server_v2node')->count());
    }

    public function testDeleteSourceClearsCache()
    {
        $source = $this->makeSource();
        $this->service($this->fetcher($this->subscribeContent()))->sync($source);
        $this->assertSame(2, $this->service()->getCachedNodeCount((int)$source->id));

        $service = $this->service();
        $service->clearCache((int)$source->id);
        $this->assertSame(0, $service->getCachedNodeCount((int)$source->id));
    }

    public function testThirdPartyCredentialUsedInUriGeneration()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $this->service($this->fetcher($this->subscribeContent()))->sync($source);

        $servers = (new ServerService())->getAvailableServers($user);
        $server = collect($servers)->firstWhere('name', 'TP-A');
        $this->assertNotEmpty($server['sub_uuid']);

        $uri = \App\Utils\Helper::buildUri($user->uuid, $server);
        $this->assertNotSame('', $uri);
        $this->assertStringContainsString($server['sub_uuid'], $uri);
        $this->assertStringNotContainsString($user->uuid, $uri);
    }

    public function testTransportLayerPassthroughForVlessWs()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $content = "vless://tp-uuid@tp-ws.example.com:443?type=ws&security=tls&sni=cdn.example.com&host=cdn.example.com&path=%2Fv2ray#TP-WS\n";
        $result = $this->service($this->fetcher($content))->sync($source);
        $this->assertTrue($result['success']);

        $servers = (new ServerService())->getAvailableServers($user);
        $server = collect($servers)->firstWhere('name', 'TP-WS');
        $this->assertNotEmpty($server);
        $this->assertSame('ws', $server['network']);
        $this->assertSame('/v2ray', $server['network_settings']['path']);
        $this->assertSame('cdn.example.com', $server['network_settings']['headers']['Host']);
        $this->assertSame('cdn.example.com', $server['tls_settings']['server_name']);
    }

    public function testTransportLayerPassthroughForTrojanWs()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $content = "trojan://tp-pass@tp-trojan-ws.example.com:443?security=tls&sni=ws.example.com&type=ws&path=%2Fassignment&host=ws.example.com&allowInsecure=0#TP-Trojan-WS\n";
        $result = $this->service($this->fetcher($content))->sync($source);
        $this->assertTrue($result['success']);

        $servers = (new ServerService())->getAvailableServers($user);
        $server = collect($servers)->firstWhere('name', 'TP-Trojan-WS');
        $this->assertNotEmpty($server);
        $this->assertSame('ws', $server['network']);
        $this->assertSame('/assignment', $server['network_settings']['path']);
        $this->assertSame('ws.example.com', $server['network_settings']['headers']['Host']);
        $this->assertSame('ws.example.com', $server['server_name']);
    }

    public function testTransportLayerPassthroughForTrojanGrpc()
    {
        $user = $this->makeUser();
        $source = $this->makeSource();
        $content = "trojan://tp-pass@tp-trojan-grpc.example.com:443?security=tls&sni=grpc.example.com&type=grpc&serviceName=test&allowInsecure=0#TP-Trojan-GRPC\n";
        $result = $this->service($this->fetcher($content))->sync($source);
        $this->assertTrue($result['success']);

        $servers = (new ServerService())->getAvailableServers($user);
        $server = collect($servers)->firstWhere('name', 'TP-Trojan-GRPC');
        $this->assertNotEmpty($server);
        $this->assertSame('grpc', $server['network']);
        $this->assertSame('test', $server['network_settings']['serviceName']);
    }

    private function nodeTableCounts(): array
    {
        $counts = [];
        foreach (['v2_server_vless', 'v2_server_vmess', 'v2_server_trojan', 'v2_server_shadowsocks', 'v2_server_tuic', 'v2_server_hysteria', 'v2_server_anytls', 'v2_server_v2node'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        return $counts;
    }
}
