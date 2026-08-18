<?php

namespace Tests\Feature;

use App\Protocols\Clash;
use App\Protocols\Loon;
use App\Protocols\QuantumultX;
use App\Protocols\Shadowrocket;
use App\Protocols\Stash;
use App\Protocols\Surfboard;
use App\Protocols\Surge;
use Tests\TestCase;

class ProtocolConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['HTTP_HOST'] = 'example.com';
        set_error_handler(function ($severity, $message, $file, $line) {
            if (strpos($message, 'headers already sent') !== false || strpos($message, 'Cannot modify header') !== false) {
                return true;
            }
            return false;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    protected function makeUser(): array
    {
        return [
            'uuid' => 'user-uuid-consistency',
            'token' => 'user-token-consistency',
            'u' => 0,
            'd' => 0,
            'transfer_enable' => 1024 * 1024 * 1024,
            'expired_at' => time() + 86400,
        ];
    }

    protected function makeServers(): array
    {
        return [
            ['type' => 'shadowsocks', 'name' => 'SS-1', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
            ['type' => 'vmess', 'name' => 'VM-1', 'host' => 'vm.example.com', 'port' => 443, 'network' => 'ws', 'tls' => 1, 'tls_settings' => ['server_name' => 'vm.example.com', 'allow_insecure' => 0], 'network_settings' => ['path' => '/vm', 'headers' => ['Host' => 'vm.example.com']], 'sub_uuid' => 'vm-pass'],
            ['type' => 'vless', 'name' => 'VL-1', 'host' => 'vl.example.com', 'port' => 443, 'network' => 'ws', 'tls' => 1, 'flow' => '', 'tls_settings' => ['server_name' => 'vl.example.com', 'fingerprint' => 'chrome', 'allow_insecure' => 0, 'public_key' => '', 'short_id' => '', 'ech' => '', 'ech_config' => ''], 'network_settings' => ['path' => '/x', 'headers' => ['Host' => 'vl.example.com']], 'sub_uuid' => 'vl-pass'],
            ['type' => 'vless', 'name' => 'VL-GRPC', 'host' => 'vlgrpc.example.com', 'port' => 443, 'network' => 'grpc', 'tls' => 1, 'flow' => '', 'tls_settings' => ['server_name' => 'vlgrpc.example.com', 'fingerprint' => 'chrome', 'allow_insecure' => 0], 'network_settings' => ['serviceName' => 'grpc-svc'], 'sub_uuid' => 'vlgrpc-pass'],
            ['type' => 'vless', 'name' => 'VL-XHTTP', 'host' => 'vlxhttp.example.com', 'port' => 443, 'network' => 'xhttp', 'tls' => 1, 'flow' => '', 'tls_settings' => ['server_name' => 'vlxhttp.example.com', 'fingerprint' => 'chrome', 'allow_insecure' => 0], 'network_settings' => ['path' => '/xhttp', 'host' => 'xhttp.example.com', 'mode' => 'auto'], 'sub_uuid' => 'vlxhttp-pass'],
            ['type' => 'vless', 'name' => 'VL-H2', 'host' => 'vlh2.example.com', 'port' => 443, 'network' => 'h2', 'tls' => 1, 'flow' => '', 'tls_settings' => ['server_name' => 'vlh2.example.com', 'fingerprint' => 'chrome', 'allow_insecure' => 0], 'network_settings' => ['path' => '/h2', 'headers' => ['Host' => 'h2.example.com']], 'sub_uuid' => 'vlh2-pass'],
            ['type' => 'trojan', 'name' => 'TJ-1', 'host' => 'tj.example.com', 'port' => 443, 'network' => 'tcp', 'server_name' => 'tj.example.com', 'allow_insecure' => 0, 'tls_settings' => ['server_name' => 'tj.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'tj-pass'],
            ['type' => 'tuic', 'name' => 'TUIC-1', 'host' => 'tuic.example.com', 'port' => 8443, 'disable_sni' => 0, 'zero_rtt_handshake' => 0, 'udp_relay_mode' => 'native', 'congestion_control' => 'cubic', 'insecure' => 0, 'server_name' => 'tuic.example.com', 'tls_settings' => ['server_name' => 'tuic.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'tuic-pass'],
            ['type' => 'hysteria', 'name' => 'HY-1', 'host' => 'hy.example.com', 'port' => '443', 'version' => 1, 'insecure' => 0, 'server_name' => 'hy.example.com', 'up_mbps' => '50', 'down_mbps' => '100', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy-pass'],
            ['type' => 'hysteria2', 'name' => 'HY2-1', 'host' => 'hy2.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'up_mbps' => '50', 'down_mbps' => '100', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
            ['type' => 'anytls', 'name' => 'ANY-1', 'host' => 'any.example.com', 'port' => 443, 'network' => 'tcp', 'insecure' => 0, 'server_name' => 'any.example.com', 'tls_settings' => ['server_name' => 'any.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'any-pass'],
        ];
    }

    public function testClashIncludesAllProtocolTypes()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Clash($user, $servers))->handle();

        foreach (['SS-1', 'VM-1', 'VL-1', 'TJ-1', 'TUIC-1', 'HY-1', 'HY2-1', 'ANY-1'] as $name) {
            $this->assertStringContainsString($name, $out, "Clash missing {$name}");
        }
        foreach (['type: vless', 'type: tuic', 'type: hysteria', 'type: hysteria2', 'type: anytls'] as $type) {
            $this->assertStringContainsString($type, $out, "Clash missing {$type}");
        }
    }

    public function testShadowrocketIncludesAllProtocolTypes()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = base64_decode((new Shadowrocket($user, $servers))->handle());

        foreach (['SS-1', 'VM-1', 'VL-1', 'TJ-1', 'TUIC-1', 'HY-1', 'HY2-1', 'ANY-1'] as $name) {
            $this->assertStringContainsString($name, $out, "Shadowrocket missing {$name}");
        }
    }

    public function testSurgeIncludesTuicAndHysteria2()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('TUIC-1=tuic', $out);
        $this->assertStringContainsString('HY2-1=hysteria2', $out);
        $this->assertStringContainsString('ANY-1=anytls', $out);
        $this->assertStringNotContainsString('VL-1', $out, 'Surge should not emit vless');
        $this->assertStringContainsString('sni=vm.example.com', $out);
        $this->assertStringContainsString('ws-path=/vm', $out);
    }

    public function testSurfboardIncludesTuicAndHysteria2()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('TUIC-1=tuic', $out);
        $this->assertStringContainsString('HY2-1=hysteria2', $out);
        $this->assertStringContainsString('ANY-1=anytls', $out);
        $this->assertStringContainsString('sni=vm.example.com', $out);
        $this->assertStringContainsString('ws-path=/vm', $out);
    }

    public function testLoonIncludesHysteria2Type()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Loon($user, $servers))->handle();

        $this->assertStringContainsString('HY2-1=hysteria2', $out);
        $this->assertStringContainsString('ANY-1=anytls', $out);
        $this->assertStringContainsString('VL-1=vless', $out);
        $this->assertStringContainsString('tls-name=vm.example.com', $out);
        $this->assertStringContainsString('path=/vm', $out);
        $this->assertStringContainsString('VL-H2=vless', $out);
        $this->assertStringContainsString('transport=http', $out);
        $this->assertStringContainsString('path=/h2', $out);
        $this->assertStringContainsString('host=h2.example.com', $out);
        $this->assertStringNotContainsString('VL-XHTTP', $out, 'Loon does not support vless xhttp');
        $this->assertStringNotContainsString('VL-GRPC', $out, 'Loon does not support vless grpc');
    }

    public function testStashIncludesXhttpVless()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Stash($user, $servers))->handle();

        $this->assertStringContainsString('VL-XHTTP', $out);
        $this->assertStringContainsString('network: xhttp', $out);
        $this->assertStringContainsString('xhttp-opts:', $out);
        $this->assertStringContainsString('path: /xhttp', $out);
        $this->assertStringContainsString('host: xhttp.example.com', $out);
        $this->assertStringContainsString('mode: auto', $out);
    }

    public function testQuantumultXIncludesVlessAndAnyTls()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = base64_decode((new QuantumultX($user, $servers))->handle());

        $this->assertStringContainsString('VL-1', $out);
        $this->assertStringContainsString('ANY-1', $out);
    }

    public function testClashHysteria2WithoutObfsPasswordOmitsObfs()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringNotContainsString('obfs-password:', $out);
    }

    public function testClashHysteria2WithObfsEmitsPassword()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $servers[] = ['type' => 'hysteria2', 'name' => 'HY2-OBFS', 'host' => 'hy2obfs.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2obfs.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2obfs.example.com', 'obfs' => 'salamander', 'obfs_password' => 'obfs-secret', 'sub_uuid' => 'hy2-obfs-pass'];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('obfs: salamander', $out);
        $this->assertStringContainsString("obfs-password: obfs-secret", $out);
    }
}
