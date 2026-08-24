<?php

namespace Tests\Feature;

use App\Protocols\Clash;
use App\Protocols\Loon;
use App\Protocols\QuantumultX;
use App\Protocols\Shadowrocket;
use App\Protocols\Singbox\Singbox;
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

        $this->assertStringContainsString('TUIC-1=tuic-v5', $out);
        $this->assertStringContainsString('uuid=tuic-pass', $out);
        $this->assertStringContainsString('password=tuic-pass', $out);
        $this->assertStringContainsString('HY2-1=hysteria2', $out);
        $this->assertStringContainsString('ANY-1=anytls', $out);
        $this->assertStringContainsString('sni=vm.example.com', $out);
        $this->assertStringContainsString('ws-path=/vm', $out);
    }

    public function testSurfboardIncludesTuicAndHysteria2()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('TUIC-1=tuic-v5', $out);
        $this->assertStringContainsString('uuid=tuic-pass', $out);
        $this->assertStringContainsString('password=tuic-pass', $out);
        $this->assertStringContainsString('HY2-1=hysteria2', $out);
        $this->assertStringContainsString('ANY-1=anytls', $out);
        $this->assertStringContainsString('sni=vm.example.com', $out);
        $this->assertStringContainsString('ws-path=/vm', $out);
    }

    public function testSurfboardHysteria2WithoutBandwidthOmitsDownloadBandwidth()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => '德国_01', 'host' => 'de.example.com', 'port' => '443', 'up_mbps' => '', 'down_mbps' => '', 'server_name' => 'de.example.com', 'insecure' => 1, 'tls_settings' => [], 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('德国_01=hysteria2', $out);
        $this->assertStringNotContainsString('download-bandwidth=', $out);
        $this->assertStringContainsString('skip-cert-verify=true', $out);
    }

    public function testSurfboardHysteria2WithSalamanderEmitsObfsPassword()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => '德国_01', 'host' => 'de.example.com', 'port' => '443', 'up_mbps' => '', 'down_mbps' => '', 'server_name' => 'de.example.com', 'insecure' => 1, 'tls_settings' => [], 'obfs' => 'salamander', 'obfs_password' => 'salamander-secret', 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('salamander-password=salamander-secret', $out);
    }

    public function testSurfboardHysteria2WithPortRangeEmitsPortHopping()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => '德国_01', 'host' => 'de.example.com', 'port' => '443-8443', 'up_mbps' => '', 'down_mbps' => '', 'server_name' => 'de.example.com', 'insecure' => 1, 'tls_settings' => [], 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('port-hopping="443-8443"', $out);
    }

    public function testSurfboardAnyTlsUsesPositionalPassword()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'anytls', 'name' => 'ANY-1', 'host' => 'any.example.com', 'port' => 443, 'network' => 'tcp', 'insecure' => 0, 'server_name' => 'any.example.com', 'tls_settings' => ['server_name' => 'any.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'any-pass'],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('ANY-1=anytls, any.example.com, 443, any-pass', $out);
        $this->assertStringNotContainsString('password=any-pass', $out);
    }

    public function testSurfboardShadowsocks2022UsesBase64Password()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-2022', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => '2022-blake3-aes-128-gcm', 'obfs' => '', 'created_at' => 1700000000, 'network' => 'tcp', 'network_settings' => []],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('SS-2022=ss', $out);
        $this->assertStringContainsString('encrypt-method=2022-blake3-aes-128-gcm', $out);
        $this->assertStringContainsString('password=', $out);
    }

    public function testSurfboardTrojanWithoutWsHostOmitsWsHeaders()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'trojan', 'name' => '法国_03', 'host' => 'fr.example.com', 'port' => '443', 'network' => 'ws', 'server_name' => 'fr.example.com', 'allow_insecure' => 0, 'network_settings' => ['path' => '/assignment', 'headers' => ['Host' => '']], 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('ws=true', $out);
        $this->assertStringContainsString('ws-path=/assignment', $out);
        $this->assertStringNotContainsString('ws-headers=Host:', $out);
    }

    public function testSurgeHysteria2WithoutBandwidthOmitsDownloadBandwidth()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => '德国_01', 'host' => 'de.example.com', 'port' => '443', 'up_mbps' => '', 'down_mbps' => '', 'server_name' => 'de.example.com', 'insecure' => 1, 'tls_settings' => [], 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('德国_01=hysteria2', $out);
        $this->assertStringNotContainsString('download-bandwidth=', $out);
        $this->assertStringContainsString('skip-cert-verify=true', $out);
    }

    public function testSingboxVlessRealityWithoutFlowDefaultsToVisionAndOmitsPacketEncoding()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'vless', 'name' => '俄罗斯_02', 'host' => '176.124.222.105', 'port' => 6443, 'network' => 'tcp', 'tls' => 2, 'flow' => '', 'tls_settings' => ['server_name' => 'api.vkimages.io', 'fingerprint' => 'qq', 'allow_insecure' => 0, 'public_key' => 'sgFF', 'short_id' => '844352756c1fabba', 'ech' => '', 'ech_config' => ''], 'network_settings' => [], 'sub_uuid' => '440f3c08'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"flow":"xtls-rprx-vision"', $out);
        $this->assertStringNotContainsString('"packet_encoding":"xudp"', $out);
        $this->assertStringContainsString('"reality"', $out);
    }

    public function testSingboxVlessNonRealityWithoutFlowEmitsPacketEncoding()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'vless', 'name' => 'VL-1', 'host' => 'vl.example.com', 'port' => 443, 'network' => 'ws', 'tls' => 1, 'flow' => '', 'tls_settings' => ['server_name' => 'vl.example.com', 'fingerprint' => 'chrome', 'allow_insecure' => 0, 'public_key' => '', 'short_id' => '', 'ech' => '', 'ech_config' => ''], 'network_settings' => ['path' => '/x', 'headers' => ['Host' => 'vl.example.com']], 'sub_uuid' => 'vl-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"packet_encoding":"xudp"', $out);
        $this->assertStringNotContainsString('"flow":"', $out);
    }

    public function testSingboxTrojanWsWithoutHostOmitsEmptyHostHeader()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'trojan', 'name' => '法国_03', 'host' => '104.26.15.137', 'port' => 443, 'network' => 'ws', 'server_name' => 'www.ignitelimit.com', 'allow_insecure' => 0, 'network_settings' => ['path' => '/assignment', 'headers' => ['Host' => '']], 'tls_settings' => ['server_name' => 'www.ignitelimit.com', 'allow_insecure' => 0], 'sub_uuid' => 'humanity'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"path":"/assignment"', $out);
        $this->assertStringNotContainsString('"Host":[""]', $out);
        $this->assertStringNotContainsString('"Host":""', $out);
    }

    public function testSingboxTuicEmitsPasswordField()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'tuic', 'name' => 'TUIC-1', 'host' => 'tuic.example.com', 'port' => 8443, 'disable_sni' => 0, 'zero_rtt_handshake' => 0, 'udp_relay_mode' => 'native', 'congestion_control' => 'cubic', 'insecure' => 0, 'server_name' => 'tuic.example.com', 'tls_settings' => ['server_name' => 'tuic.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'tuic-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"uuid":"tuic-pass"', $out);
        $this->assertStringContainsString('"password":"tuic-pass"', $out);
    }

    public function testSingboxVmessHttpTransportHostIsArray()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'vmess', 'name' => 'VM-HTTP', 'host' => 'vm.example.com', 'port' => 80, 'network' => 'tcp', 'tls' => 0, 'network_settings' => ['header' => ['type' => 'http', 'request' => ['headers' => ['Host' => 'vm.example.com'], 'path' => ['/']]]], 'sub_uuid' => 'vm-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"type":"http"', $out);
        $this->assertStringContainsString('"host":["vm.example.com"]', $out);
        $this->assertStringNotContainsString('"host":"vm.example.com"', $out);
    }

    public function testSingboxHysteriaV1WithoutBandwidthOmitsUpDownMbps()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria', 'name' => 'HY-1', 'host' => 'hy.example.com', 'port' => '443', 'version' => 1, 'insecure' => 0, 'server_name' => 'hy.example.com', 'up_mbps' => '', 'down_mbps' => '', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"type":"hysteria"', $out);
        $this->assertStringNotContainsString('"up_mbps"', $out);
        $this->assertStringNotContainsString('"down_mbps"', $out);
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

    public function testClashVmessEmitsServernameFromSnakeCaseTlsSettings()
    {
        $user = $this->makeUser();
        $servers = $this->makeServers();
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('servername: vm.example.com', $out);
    }

    public function testClashVmessEmitsSkipCertVerifyTrue()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'vmess', 'name' => 'VM-TLS', 'host' => 'vmtls.example.com', 'port' => 443, 'network' => 'tcp', 'tls' => 1, 'tls_settings' => ['server_name' => 'vmtls.example.com', 'allow_insecure' => 1], 'network_settings' => [], 'sub_uuid' => 'vm-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('servername: vmtls.example.com', $out);
        $this->assertStringContainsString('skip-cert-verify: true', $out);
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

    public function testClashTuicUsesUuidAndPassword()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'tuic', 'name' => 'TUIC-1', 'host' => 'tuic.example.com', 'port' => 8443, 'disable_sni' => 0, 'zero_rtt_handshake' => 0, 'udp_relay_mode' => 'native', 'congestion_control' => 'cubic', 'insecure' => 0, 'server_name' => 'tuic.example.com', 'tls_settings' => ['server_name' => 'tuic.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'tuic-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('uuid: tuic-pass', $out);
        $this->assertStringContainsString('password: tuic-pass', $out);
        $this->assertStringNotContainsString('token: tuic-pass', $out);
    }

    public function testClashHysteriaUsesAuthStrHyphen()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria', 'name' => 'HY-1', 'host' => 'hy.example.com', 'port' => '443', 'version' => 1, 'insecure' => 0, 'server_name' => 'hy.example.com', 'up_mbps' => '50', 'down_mbps' => '100', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('auth-str: hy-pass', $out);
        $this->assertStringNotContainsString('auth_str:', $out);
    }

    public function testClashHysteriaWithoutBandwidthOmitsUpDown()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria', 'name' => 'HY-EMPTY', 'host' => 'hy.example.com', 'port' => '443', 'version' => 1, 'insecure' => 0, 'server_name' => 'hy.example.com', 'up_mbps' => '', 'down_mbps' => '', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringNotContainsString('up:', $out);
        $this->assertStringNotContainsString('down:', $out);
    }

    public function testClashHysteria2OmitsMport()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-PORTS', 'host' => 'hy2.example.com', 'port' => '443-8443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('ports:', $out);
        $this->assertStringNotContainsString('mport:', $out);
    }

    public function testClashVlessEncryptionDefaultsModeAndRtt()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'vless', 'name' => 'VL-ENC', 'host' => 'vl.example.com', 'port' => 443, 'network' => 'tcp', 'tls' => 1, 'flow' => '', 'encryption' => 'mlkem768x25519plus', 'encryption_settings' => ['mode' => '', 'rtt' => '', 'password' => 'secret'], 'tls_settings' => ['server_name' => 'vl.example.com', 'fingerprint' => 'chrome', 'allow_insecure' => 0], 'network_settings' => [], 'sub_uuid' => 'vl-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('encryption: mlkem768x25519plus.native.1rtt.secret', $out);
    }

    public function testSurgeShadowsocksUsesUdpRelayAndTfo()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-1', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('udp-relay=true', $out);
        $this->assertStringContainsString('tfo=true', $out);
        $this->assertStringNotContainsString('fast-open=', $out);
        $this->assertStringNotContainsString('udp=true', $out);
    }

    public function testSurgeHysteria2WithSalamanderEmitsObfsPassword()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => '德国_01', 'host' => 'de.example.com', 'port' => '443', 'up_mbps' => '', 'down_mbps' => '', 'server_name' => 'de.example.com', 'insecure' => 1, 'tls_settings' => [], 'obfs' => 'salamander', 'obfs_password' => 'salamander-secret', 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('salamander-password=salamander-secret', $out);
    }

    public function testSurgeHysteria2WithPortRangeEmitsPortHopping()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => '德国_01', 'host' => 'de.example.com', 'port' => '443-8443', 'up_mbps' => '', 'down_mbps' => '', 'server_name' => 'de.example.com', 'insecure' => 1, 'tls_settings' => [], 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'pass1'],
        ];
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('port-hopping="443-8443"', $out);
    }

    public function testSurgeAnyTlsEmitsReuseFalse()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'anytls', 'name' => 'ANY-1', 'host' => 'any.example.com', 'port' => 443, 'network' => 'tcp', 'insecure' => 0, 'server_name' => 'any.example.com', 'tls_settings' => ['server_name' => 'any.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'any-pass'],
        ];
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('reuse=false', $out);
    }

    public function testSingboxHysteria2WithPortRangeEmitsServerPorts()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-PORTS', 'host' => 'hy2.example.com', 'port' => '443-8443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"server_ports":["443:8443"]', $out);
        $this->assertStringContainsString('"hop_interval":"30s"', $out);
    }

    public function testSingboxHysteria2SinglePortOmitsServerPorts()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-1', 'host' => 'hy2.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"server_port":443', $out);
        $this->assertStringNotContainsString('server_ports', $out);
    }

    public function testSingboxVmessHttpupgradeTransport()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'vmess', 'name' => 'VM-HTTPUP', 'host' => 'vm.example.com', 'port' => 443, 'network' => 'httpupgrade', 'tls' => 1, 'network_settings' => ['path' => '/upgrade', 'host' => 'vm.example.com'], 'sub_uuid' => 'vm-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"type":"httpupgrade"', $out);
        $this->assertStringContainsString('"host":"vm.example.com"', $out);
        $this->assertStringContainsString('"path":"/upgrade"', $out);
    }

    public function testClashHysteria2EmitsBrutalUpDown()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-BW', 'host' => 'hy2.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'up_mbps' => '50', 'down_mbps' => '100', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString("up: '100'", $out);
        $this->assertStringContainsString("down: '50'", $out);
    }

    public function testClashHysteria2WithoutBandwidthOmitsUpDown()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-EMPTY', 'host' => 'hy2.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'up_mbps' => '', 'down_mbps' => '', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringNotContainsString('up:', $out);
        $this->assertStringNotContainsString('down:', $out);
    }

    public function testSurgeVmessWsSecurityMapsEncryptMethod()
    {
        $user = $this->makeUser();
        $base = ['type' => 'vmess', 'name' => 'VM-WS', 'host' => 'vm.example.com', 'port' => 443, 'network' => 'ws', 'tls' => 0, 'sub_uuid' => 'vm-pass'];

        $servers = [$base + ['network_settings' => ['path' => '/ws', 'security' => 'chacha20-poly1305']]];
        $out = (new Surge($user, $servers))->handle();
        $this->assertStringContainsString('encrypt-method=chacha20-ietf-poly1305', $out);
        $this->assertStringNotContainsString('encrypt-method=chacha20-poly1305', $out);

        $servers = [$base + ['network_settings' => ['path' => '/ws', 'security' => 'auto']]];
        $out = (new Surge($user, $servers))->handle();
        $this->assertStringNotContainsString('encrypt-method=', $out);
    }

    public function testSurfboardVmessWsSecurityMapsEncryptMethod()
    {
        $user = $this->makeUser();
        $base = ['type' => 'vmess', 'name' => 'VM-WS', 'host' => 'vm.example.com', 'port' => 443, 'network' => 'ws', 'tls' => 0, 'sub_uuid' => 'vm-pass'];

        $servers = [$base + ['network_settings' => ['path' => '/ws', 'security' => 'chacha20-poly1305']]];
        $out = (new Surfboard($user, $servers))->handle();
        $this->assertStringContainsString('encrypt-method=chacha20-ietf-poly1305', $out);
        $this->assertStringNotContainsString('encrypt-method=chacha20-poly1305', $out);

        $servers = [$base + ['network_settings' => ['path' => '/ws', 'security' => 'none']]];
        $out = (new Surfboard($user, $servers))->handle();
        $this->assertStringNotContainsString('encrypt-method=', $out);
    }

    public function testSurgeShadowsocksTlsObfs()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-TLS', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => 'tls', 'obfs-host' => 'obfs.example.com', 'obfs-path' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = (new Surge($user, $servers))->handle();

        $this->assertStringContainsString('obfs=tls', $out);
        $this->assertStringContainsString('obfs-host=obfs.example.com', $out);
        $this->assertStringNotContainsString('obfs-uri=', $out);
    }

    public function testSurfboardShadowsocksTlsObfs()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-TLS', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => 'tls', 'obfs-host' => 'obfs.example.com', 'obfs-path' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = (new Surfboard($user, $servers))->handle();

        $this->assertStringContainsString('obfs=tls', $out);
        $this->assertStringContainsString('obfs-host=obfs.example.com', $out);
        $this->assertStringNotContainsString('obfs-uri=', $out);
    }

    public function testLoonShadowsocksTlsObfs()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-TLS', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => 'tls', 'obfs-host' => 'obfs.example.com', 'obfs-path' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = (new Loon($user, $servers))->handle();

        $this->assertStringContainsString('obfs-name=tls', $out);
        $this->assertStringContainsString('obfs-host=obfs.example.com', $out);
        $this->assertStringNotContainsString('obfs-uri=', $out);
    }

    public function testSingboxShadowsocksTlsObfs()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-TLS', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => 'tls', 'obfs-host' => 'obfs.example.com', 'obfs-path' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = (new Singbox($user, $servers))->handle()->getContent();

        $this->assertStringContainsString('"plugin":"obfs-local"', $out);
        $this->assertStringContainsString('obfs=tls', $out);
        $this->assertStringContainsString('obfs-host=obfs.example.com', $out);
    }

    public function testClashShadowsocksTlsObfs()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-TLS', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => 'tls', 'obfs-host' => 'obfs.example.com', 'obfs-path' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = (new Clash($user, $servers))->handle();

        $this->assertStringContainsString('plugin: obfs', $out);
        $this->assertStringContainsString("mode: tls", $out);
        $this->assertStringContainsString('host: obfs.example.com', $out);
    }

    public function testQuantumultXShadowsocksTlsObfs()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'shadowsocks', 'name' => 'SS-TLS', 'host' => 'ss.example.com', 'port' => 8388, 'cipher' => 'aes-256-gcm', 'obfs' => 'tls', 'obfs-host' => 'obfs.example.com', 'obfs-path' => '', 'created_at' => time(), 'network' => 'tcp', 'network_settings' => [], 'sub_uuid' => 'ss-pass'],
        ];
        $out = base64_decode((new QuantumultX($user, $servers))->handle());

        $this->assertStringContainsString('obfs=tls', $out);
        $this->assertStringContainsString('obfs-host=obfs.example.com', $out);
    }

    public function testStashHysteria2EmitsBrutalUpDown()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-BW', 'host' => 'hy2.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'up_mbps' => '50', 'down_mbps' => '100', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Stash($user, $servers))->handle();

        $this->assertStringContainsString("up: '100'", $out);
        $this->assertStringContainsString("down: '50'", $out);
    }

    public function testStashHysteria2WithoutBandwidthOmitsUpDown()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'hysteria2', 'name' => 'HY2-EMPTY', 'host' => 'hy2.example.com', 'port' => '443', 'tls_settings' => ['server_name' => 'hy2.example.com', 'allow_insecure' => 0], 'insecure' => 0, 'server_name' => 'hy2.example.com', 'up_mbps' => '', 'down_mbps' => '', 'obfs' => '', 'obfs_password' => '', 'sub_uuid' => 'hy2-pass'],
        ];
        $out = (new Stash($user, $servers))->handle();

        $this->assertStringNotContainsString('up:', $out);
        $this->assertStringNotContainsString('down:', $out);
    }

    public function testStashTuicEmitsTuningOptions()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'tuic', 'name' => 'TUIC-1', 'host' => 'tuic.example.com', 'port' => 8443, 'disable_sni' => 0, 'zero_rtt_handshake' => 0, 'udp_relay_mode' => 'native', 'congestion_control' => 'cubic', 'insecure' => 0, 'server_name' => 'tuic.example.com', 'tls_settings' => ['server_name' => 'tuic.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'tuic-pass'],
        ];
        $out = (new Stash($user, $servers))->handle();

        $this->assertStringContainsString('disable-sni: false', $out);
        $this->assertStringContainsString('reduce-rtt: false', $out);
        $this->assertStringContainsString('udp-relay-mode: native', $out);
        $this->assertStringContainsString('congestion-controller: cubic', $out);
    }

    public function testStashAnyTlsEmitsUdpAndAlpn()
    {
        $user = $this->makeUser();
        $servers = [
            ['type' => 'anytls', 'name' => 'ANY-1', 'host' => 'any.example.com', 'port' => 443, 'network' => 'tcp', 'insecure' => 0, 'server_name' => 'any.example.com', 'tls_settings' => ['server_name' => 'any.example.com', 'allow_insecure' => 0], 'sub_uuid' => 'any-pass'],
        ];
        $out = (new Stash($user, $servers))->handle();

        $this->assertStringContainsString('udp: true', $out);
        $this->assertStringContainsString('alpn:', $out);
        $this->assertStringContainsString('h2', $out);
        $this->assertStringContainsString('http/1.1', $out);
    }
}
