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
}
