<?php

namespace Tests\Unit\ThirdParty;

use App\Services\ThirdParty\Parsers\AnytlsParser;
use App\Services\ThirdParty\Parsers\Base64UriListParser;
use App\Services\ThirdParty\Parsers\ClashYamlParser;
use App\Services\ThirdParty\Parsers\Hysteria2Parser;
use App\Services\ThirdParty\Parsers\ShadowsocksParser;
use App\Services\ThirdParty\Parsers\TrojanParser;
use App\Services\ThirdParty\Parsers\TuicParser;
use App\Services\ThirdParty\Parsers\UriListParser;
use App\Services\ThirdParty\Parsers\VlessParser;
use App\Services\ThirdParty\Parsers\VmessParser;
use App\Services\ThirdParty\SubscriptionParserManager;
use PHPUnit\Framework\TestCase;

class ParsersTest extends TestCase
{
    public function testParseVlessUri()
    {
        $node = VlessParser::parse('vless://a1b2c3d4-1111-2222-3333-444455556666@example.com:443?type=ws&security=tls&sni=cdn.example.com&fp=chrome&host=cdn.example.com&path=%2Fv2ray&flow=xtls-rprx-vision#HK%20Node');
        $this->assertNotNull($node);
        $this->assertSame('vless', $node->type);
        $this->assertSame('HK Node', $node->name);
        $this->assertSame('example.com', $node->server);
        $this->assertSame(443, $node->port);
        $this->assertSame('a1b2c3d4-1111-2222-3333-444455556666', $node->settings['credential']);
        $this->assertSame('ws', $node->settings['type']);
        $this->assertSame('ws', $node->settings['network']);
        $this->assertSame('tls', $node->settings['security']);
        $this->assertSame('cdn.example.com', $node->settings['sni']);
        $this->assertSame('/v2ray', $node->settings['path']);
        $this->assertSame('xtls-rprx-vision', $node->settings['flow']);
    }

    public function testParseVlessReality()
    {
        $node = VlessParser::parse('vless://uuid@jp.example.com:443?type=tcp&security=reality&pbk=pbk123&sid=sid456&fp=chrome&sni=www.microsoft.com#JP-Reality');
        $this->assertNotNull($node);
        $this->assertSame('reality', $node->settings['security']);
        $this->assertSame('pbk123', $node->settings['pbk']);
        $this->assertSame('sid456', $node->settings['sid']);
        $this->assertSame('www.microsoft.com', $node->settings['sni']);
    }

    public function testParseVmessUri()
    {
        $payload = base64_encode(json_encode([
            'v' => '2',
            'ps' => 'US Node',
            'add' => 'us.example.com',
            'port' => '8080',
            'id' => 'uuid-1234',
            'aid' => '0',
            'net' => 'ws',
            'type' => 'none',
            'host' => 'us.example.com',
            'path' => '/ws',
            'tls' => 'tls',
            'sni' => 'us.example.com',
        ]));
        $node = VmessParser::parse("vmess://{$payload}");
        $this->assertNotNull($node);
        $this->assertSame('vmess', $node->type);
        $this->assertSame('US Node', $node->name);
        $this->assertSame('us.example.com', $node->server);
        $this->assertSame(8080, $node->port);
        $this->assertSame('uuid-1234', $node->settings['credential']);
        $this->assertSame('ws', $node->settings['network']);
        $this->assertSame('tls', $node->settings['tls']);
    }

    public function testParseTrojanUri()
    {
        $node = TrojanParser::parse('trojan://trojan-pass@trojan.example.com:443?security=tls&sni=trojan.example.com&type=grpc&serviceName=test&allowInsecure=0#TrojanNode');
        $this->assertNotNull($node);
        $this->assertSame('trojan', $node->type);
        $this->assertSame('TrojanNode', $node->name);
        $this->assertSame('trojan.example.com', $node->server);
        $this->assertSame(443, $node->port);
        $this->assertSame('trojan-pass', $node->settings['credential']);
        $this->assertSame('grpc', $node->settings['type']);
        $this->assertSame('grpc', $node->settings['network']);
        $this->assertSame('test', $node->settings['serviceName']);
    }

    public function testParseTrojanGoWebsocketObfs()
    {
        $node = TrojanParser::parse('trojan://humanity@104.26.15.137:443?peer=www.ignitelimit.com&plugin=obfs-local;obfs%3Dwebsocket;obfs-host%3D;obfs-uri%3D/assignment#Node');
        $this->assertNotNull($node);
        $this->assertSame('trojan', $node->type);
        $this->assertSame('www.ignitelimit.com', $node->settings['sni']);
        $this->assertSame('ws', $node->settings['network']);
        $this->assertSame('/assignment', $node->settings['path']);
        $this->assertSame('www.ignitelimit.com', $node->settings['host']);
    }

    public function testParseShadowsocksUri()
    {
        $userPass = base64_encode('aes-256-gcm:ss-password');
        $node = ShadowsocksParser::parse("ss://{$userPass}@ss.example.com:8388#SSNode");
        $this->assertNotNull($node);
        $this->assertSame('shadowsocks', $node->type);
        $this->assertSame('SSNode', $node->name);
        $this->assertSame('ss.example.com', $node->server);
        $this->assertSame(8388, $node->port);
        $this->assertSame('aes-256-gcm', $node->settings['method']);
        $this->assertSame('ss-password', $node->settings['credential']);
    }

    public function testParseShadowsocksSip002WithPlugin()
    {
        $userPass = base64_encode('chacha20-ietf-poly1305:secret');
        $uri = "ss://{$userPass}@ss2.example.com:443?plugin=obfs-local%3Bobfs%3Dhttp%3Bobfs-host%3Dexample.com%3Bpath%3D%2Fproxy#SS-Obfs";
        $node = ShadowsocksParser::parse($uri);
        $this->assertNotNull($node);
        $this->assertSame('http', $node->settings['obfs']);
        $this->assertSame('example.com', $node->settings['obfs_host']);
        $this->assertSame('/proxy', $node->settings['obfs_path']);
    }

    public function testParseHysteria2Uri()
    {
        $node = Hysteria2Parser::parse('hysteria2://hy2-pass@hy2.example.com:8443/?insecure=1&sni=hy2.example.com#Hy2Node');
        $this->assertNotNull($node);
        $this->assertSame('hysteria2', $node->type);
        $this->assertSame('hy2-pass', $node->settings['credential']);
        $this->assertSame('1', $node->settings['insecure']);
        $this->assertSame('hy2.example.com', $node->settings['sni']);
    }

    public function testParseHysteria2UriWithObfsPassword()
    {
        $node = Hysteria2Parser::parse('hysteria2://hy2-pass@hy2.example.com:8443/?insecure=1&sni=hy2.example.com&obfs=salamander&obfs-password=obfs-secret#Hy2Node');
        $this->assertNotNull($node);
        $this->assertSame('salamander', $node->settings['obfs']);
        $this->assertSame('obfs-secret', $node->settings['obfs_password']);
    }

    public function testParseHysteria2UriWithMport()
    {
        $node = Hysteria2Parser::parse('hysteria2://hy2-pass@hy2.example.com:8443/?insecure=1&sni=hy2.example.com&mport=20000-50000#Hy2Node');
        $this->assertNotNull($node);
        $this->assertSame('20000-50000', $node->settings['ports']);
    }

    public function testParseTuicUri()
    {
        $node = TuicParser::parse('tuic://tuic-uuid:tuic-pass@tuic.example.com:7788?sni=tuic.example.com&congestion_control=bbr#TuicNode');
        $this->assertNotNull($node);
        $this->assertSame('tuic', $node->type);
        $this->assertSame('tuic-pass', $node->settings['credential']);
        $this->assertSame('bbr', $node->settings['congestion_control']);
    }

    public function testParseAnytlsUri()
    {
        $node = AnytlsParser::parse('anytls://anytls-pass@anytls.example.com:443/?sni=apple.com&insecure=0#AnyTLSNode');
        $this->assertNotNull($node);
        $this->assertSame('anytls', $node->type);
        $this->assertSame('anytls-pass', $node->settings['credential']);
        $this->assertSame('apple.com', $node->settings['sni']);
    }

    public function testParseUriList()
    {
        $content = "vless://uuid1@a.example.com:443?type=tcp&security=tls#A\n"
            . "vmess://" . base64_encode(json_encode(['ps' => 'B', 'add' => 'b.example.com', 'port' => '443', 'id' => 'uuid2', 'net' => 'tcp'])) . "\n"
            . "trojan://pass3@c.example.com:443#C\n"
            . "ss://" . base64_encode('aes-128-gcm:pw4') . "@d.example.com:443#D\n";
        $parser = new UriListParser();
        $this->assertTrue($parser->supports($content));
        $nodes = $parser->parse($content);
        $this->assertCount(4, $nodes);
        $this->assertSame(['vless', 'vmess', 'trojan', 'shadowsocks'], array_column(array_map(fn($n) => $n->toArray(), $nodes), 'type'));
    }

    public function testParseUriListIgnoresBrokenLines()
    {
        $content = "vless://uuid1@a.example.com:443#A\n"
            . "this is not a valid uri\n"
            . "ss://not-base64-@@@\n"
            . "trojan://pass3@c.example.com:443#C\n";
        $parser = new UriListParser();
        $nodes = $parser->parse($content);
        $this->assertCount(2, $nodes);
    }

    public function testParseBase64UriList()
    {
        $content = "vless://uuid1@a.example.com:443?type=tcp&security=tls#A\n"
            . "trojan://pass3@c.example.com:443#C\n";
        $parser = new Base64UriListParser();
        $this->assertTrue($parser->supports(base64_encode($content)));
        $nodes = $parser->parse(base64_encode($content));
        $this->assertCount(2, $nodes);
    }

    public function testParseClashYaml()
    {
        $yaml = <<<YAML
proxies:
  - name: "CLASH-VLESS"
    type: vless
    server: clash.example.com
    port: 443
    uuid: clash-uuid
    network: ws
    tls: true
    servername: clash.example.com
    ws-opts:
      path: /clash
      headers:
        Host: clash.example.com
  - name: "CLASH-TROJAN"
    type: trojan
    server: t.example.com
    port: 443
    password: troj-pw
    sni: t.example.com
  - name: "CLASH-SS"
    type: ss
    server: s.example.com
    port: 8388
    cipher: aes-256-gcm
    password: ss-pw
YAML;
        $parser = new ClashYamlParser();
        $this->assertTrue($parser->supports($yaml));
        $nodes = $parser->parse($yaml);
        $this->assertCount(3, $nodes);
        $this->assertSame('vless', $nodes[0]->type);
        $this->assertSame('clash-uuid', $nodes[0]->settings['credential']);
        $this->assertSame('trojan', $nodes[1]->type);
        $this->assertSame('troj-pw', $nodes[1]->settings['credential']);
        $this->assertSame('shadowsocks', $nodes[2]->type);
        $this->assertSame('ss-pw', $nodes[2]->settings['credential']);
    }

    public function testParseClashYamlHysteria()
    {
        $yaml = <<<YAML
proxies:
  - name: "CLASH-HY"
    type: hysteria
    server: hy.example.com
    port: 443
    auth-str: hy-auth
    up: 50
    down: 100
  - name: "CLASH-HY2"
    type: hysteria2
    server: hy2.example.com
    port: 443
    password: hy2-pass
    ports: 20000-50000
YAML;
        $parser = new ClashYamlParser();
        $nodes = $parser->parse($yaml);
        $this->assertCount(2, $nodes);
        $this->assertSame('hysteria', $nodes[0]->type);
        $this->assertSame('50', $nodes[0]->settings['up_mbps']);
        $this->assertSame('100', $nodes[0]->settings['down_mbps']);
        $this->assertSame('hysteria2', $nodes[1]->type);
        $this->assertSame('20000-50000', $nodes[1]->settings['ports']);
    }

    public function testParserManagerIgnoresInvalidContent()
    {
        $manager = new SubscriptionParserManager();
        $this->assertSame([], $manager->parse(''));
        $this->assertSame([], $manager->parse('this is definitely not a subscription'));
        $this->assertSame([], $manager->parse('<>'));
        $this->assertSame([], $manager->parse('not a yaml: [broken'));
    }

    public function testParserManagerParsesUnknownFormatGracefully()
    {
        $manager = new SubscriptionParserManager();
        $this->assertSame([], $manager->parse('just-some-garbage'));
    }
}
