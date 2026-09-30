<?php

namespace Tests\Unit\ThirdParty;

use App\Services\ThirdParty\TemporaryNode;
use App\Services\ThirdParty\TemporaryNodeConverter;
use PHPUnit\Framework\TestCase;

class TemporaryNodeConverterTest extends TestCase
{
    public function testHysteriaYamlUpDownMapped()
    {
        $node = new TemporaryNode('hysteria', 'HY', 'hy.example.com', 443, [
            'credential' => 'hy-pass',
            'up_mbps' => '50',
            'down_mbps' => '100',
            'insecure' => 0,
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame('50', $array['up_mbps']);
        $this->assertSame('100', $array['down_mbps']);
    }

    public function testHysteriaUriUpDownMapped()
    {
        $node = new TemporaryNode('hysteria', 'HY', 'hy.example.com', 443, [
            'credential' => 'hy-pass',
            'upmbps' => '30',
            'downmbps' => '60',
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame('30', $array['up_mbps']);
        $this->assertSame('60', $array['down_mbps']);
    }

    public function testHysteria2PortsFoldedIntoPort()
    {
        $node = new TemporaryNode('hysteria2', 'HY2', 'hy2.example.com', 443, [
            'credential' => 'hy2-pass',
            'ports' => '20000-50000',
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame('443,20000-50000', $array['port']);
    }

    public function testHysteria2MportAlias()
    {
        $node = new TemporaryNode('hysteria2', 'HY2', 'hy2.example.com', 443, [
            'credential' => 'hy2-pass',
            'mport' => '20000-50000',
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame('443,20000-50000', $array['port']);
    }

    public function testHysteriaWithoutPortsKeepsPort()
    {
        $node = new TemporaryNode('hysteria2', 'HY2', 'hy2.example.com', 443, [
            'credential' => 'hy2-pass',
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame(443, $array['port']);
    }

    public function testVmessAllowInsecureNormalizedToSnakeCase()
    {
        $node = new TemporaryNode('vmess', 'VM', 'vm.example.com', 443, [
            'credential' => 'uuid',
            'tls' => 'tls',
            'sni' => 'vm.example.com',
            'allowInsecure' => 1,
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame(1, $array['tls_settings']['allow_insecure']);
        $this->assertSame('vm.example.com', $array['tls_settings']['server_name']);
    }

    public function testVlessAllowInsecureSnakeCaseNormalized()
    {
        $node = new TemporaryNode('vless', 'VL', 'vl.example.com', 443, [
            'credential' => 'uuid',
            'security' => 'tls',
            'sni' => 'vl.example.com',
            'allow_insecure' => 1,
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame(1, $array['tls_settings']['allow_insecure']);
    }

    public function testTuicAllowInsecureSnakeCaseNormalized()
    {
        $node = new TemporaryNode('tuic', 'TU', 'tu.example.com', 443, [
            'credential' => 'uuid',
            'allow_insecure' => 1,
            'sni' => 'tu.example.com',
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame(1, $array['insecure']);
        $this->assertSame(1, $array['tls_settings']['allow_insecure']);
    }

    public function testTrojanWebsocketObfsMapsNetworkSettings()
    {
        $node = new TemporaryNode('trojan', 'TJ', '104.26.15.137', 443, [
            'credential' => 'humanity',
            'network' => 'ws',
            'sni' => 'www.ignitelimit.com',
            'path' => '/assignment',
            'host' => 'www.ignitelimit.com',
        ]);
        $array = (new TemporaryNodeConverter())->convert($node, 1);
        $this->assertSame('ws', $array['network']);
        $this->assertSame('www.ignitelimit.com', $array['server_name']);
        $this->assertSame('/assignment', $array['network_settings']['path']);
        $this->assertSame('www.ignitelimit.com', $array['network_settings']['headers']['Host']);
    }

    public function testControlCharactersAreStrippedFromNodeStrings()
    {
        // Double-encoded flag emoji "🇨🇳" becomes "ð\u009f\u0087¨ð\u009f\u0087³",
        // which contains C1 control code points that break Clash's YAML parser.
        $doubleEncoded = "https://t.me/wangcai2\xc3\xb0\xc2\x9f\xc2\x87\xc2\xa8\xc3\xb0\xc2\x9f\xc2\x87\xc2\xb3";
        $node = new TemporaryNode('hysteria2', "HK\x07\x1f 02", '1.2.3.4', 443, [
            'credential' => 'pw',
            'sni' => $doubleEncoded,
        ]);

        $this->assertSame('HK 02', $node->name);
        $this->assertSame("https://t.me/wangcai2\u{1F1E8}\u{1F1F3}", $node->settings['sni']);
        $this->assertSame(0, preg_match('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $node->settings['sni']));

        $array = (new TemporaryNodeConverter())->convert($node, 7);
        $this->assertSame('HK 02', $array['name']);
        $this->assertSame("https://t.me/wangcai2\u{1F1E8}\u{1F1F3}", $array['tls_settings']['server_name']);
    }

    public function testSanitizeStringKeepsValidUtf8AndEmoji()
    {
        // A correctly encoded flag emoji must survive untouched.
        $this->assertSame("CN \u{1F1E8}\u{1F1F3}", TemporaryNode::sanitizeString("CN \u{1F1E8}\u{1F1F3}"));
        $this->assertSame('中国 香港', TemporaryNode::sanitizeString('中国 香港'));
        $this->assertSame("tab\tkept", TemporaryNode::sanitizeString("tab\tkept"));
    }
}
