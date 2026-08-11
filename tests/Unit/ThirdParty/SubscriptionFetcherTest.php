<?php

namespace Tests\Unit\ThirdParty;

use App\Services\ThirdParty\SubscriptionFetchException;
use App\Services\ThirdParty\SubscriptionFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SubscriptionFetcherTest extends TestCase
{
    private function fetcher(array $queue, array $records = [], int $maxBody = 2097152): SubscriptionFetcher
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $client = new Client(['handler' => $stack]);
        $resolver = function () use ($records) {
            return $records;
        };
        return new SubscriptionFetcher($client, $resolver, $maxBody);
    }

    private function publicRecords(): array
    {
        return [['type' => 'A', 'host' => 'example.com', 'ip' => '93.184.216.34']];
    }

    public function testFetch200TextContent()
    {
        $fetcher = $this->fetcher([
            new Response(200, ['Content-Type' => 'text/plain'], "vless://uuid@a.example.com:443#A"),
        ], $this->publicRecords());
        $content = $fetcher->fetch('https://example.com/sub?token=secret');
        $this->assertStringContainsString('vless://uuid@a.example.com:443#A', $content);
    }

    public function testFetch200WithEmptyContentType()
    {
        $fetcher = $this->fetcher([
            new Response(200, [], 'content'),
        ], $this->publicRecords());
        $this->assertSame('content', $fetcher->fetch('https://example.com/sub'));
    }

    public function testFetch404()
    {
        $fetcher = $this->fetcher([
            new Response(404, ['Content-Type' => 'text/plain'], 'not found'),
        ], $this->publicRecords());
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('unexpected http status: 404');
        $fetcher->fetch('https://example.com/sub');
    }

    public function testFetch500()
    {
        $fetcher = $this->fetcher([
            new Response(500, ['Content-Type' => 'text/plain'], 'server error'),
        ], $this->publicRecords());
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('unexpected http status: 500');
        $fetcher->fetch('https://example.com/sub');
    }

    public function testFetchNetworkTimeout()
    {
        $fetcher = $this->fetcher([
            new \GuzzleHttp\Exception\ConnectException('cURL error 28: Operation timed out', new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/sub')),
        ], $this->publicRecords());
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('https://example.com/sub');
    }

    public function testFetchFollowsRedirect()
    {
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => 'https://example.com/real'], ''),
            new Response(200, ['Content-Type' => 'text/plain'], 'redirected-content'),
        ], $this->publicRecords());
        $this->assertSame('redirected-content', $fetcher->fetch('https://example.com/sub'));
    }

    public function testFetchRejectsLargeResponse()
    {
        $fetcher = $this->fetcher([
            new Response(200, ['Content-Type' => 'text/plain'], str_repeat('a', 2048)),
        ], $this->publicRecords(), 1024);
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('response body exceeds size limit');
        $fetcher->fetch('https://example.com/sub');
    }

    public function testFetchRejectsBinaryContentType()
    {
        $fetcher = $this->fetcher([
            new Response(200, ['Content-Type' => 'image/png'], 'PNG data'),
        ], $this->publicRecords());
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('unexpected content-type');
        $fetcher->fetch('https://example.com/sub');
    }

    public function testRejectLoopbackIpLiteral()
    {
        $fetcher = $this->fetcher([], []);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('https://127.0.0.1/sub');
    }

    public function testRejectPrivateIpLiteral()
    {
        $fetcher = $this->fetcher([], []);
        foreach (['10.0.0.1', '172.16.0.1', '192.168.1.1', '169.254.169.254'] as $ip) {
            try {
                $fetcher->fetch("https://{$ip}/sub");
                $this->fail("should have rejected {$ip}");
            } catch (SubscriptionFetchException $e) {
                $this->assertStringContainsString('not reachable', $e->getMessage());
            }
        }
    }

    public function testRejectHostnameResolvingToPrivateIp()
    {
        $fetcher = $this->fetcher([], [['type' => 'A', 'host' => 'internal.local', 'ip' => '10.0.0.5']]);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('https://internal.local/sub');
    }

    public function testRejectHostnameResolvingToLinkLocal()
    {
        $fetcher = $this->fetcher([], [['type' => 'A', 'host' => 'metadata.local', 'ip' => '169.254.169.254']]);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('https://metadata.local/latest/meta-data/');
    }

    public function testRejectUnresolvableHostname()
    {
        $fetcher = $this->fetcher([], []);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('https://nonexistent.invalid/sub');
    }

    public function testRejectIpv6Loopback()
    {
        $fetcher = $this->fetcher([], []);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('http://[::1]/sub');
    }

    public function testRejectNonHttpScheme()
    {
        $fetcher = $this->fetcher([], []);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('file:///etc/passwd');
    }

    public function testRejectUserInfoInUrl()
    {
        $fetcher = $this->fetcher([], []);
        $this->expectException(SubscriptionFetchException::class);
        $fetcher->fetch('https://user:pass@example.com/sub');
    }

    public function testRejectRedirectToPrivateIp()
    {
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => 'http://10.0.0.5/steal'], ''),
        ], $this->publicRecords());
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('not reachable');
        $fetcher->fetch('https://example.com/sub');
    }

    public function testRejectRedirectToPrivateHostname()
    {
        $records = [
            ['type' => 'A', 'host' => 'example.com', 'ip' => '93.184.216.34'],
            ['type' => 'A', 'host' => 'metadata.local', 'ip' => '169.254.169.254'],
        ];
        $fetcher = $this->fetcher([
            new Response(301, ['Location' => 'http://metadata.local/latest/meta-data/'], ''),
        ], $records);
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('non-public ip');
        $fetcher->fetch('https://example.com/sub');
    }

    public function testRejectRedirectLoop()
    {
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => 'https://example.com/a'], ''),
            new Response(302, ['Location' => 'https://example.com/b'], ''),
            new Response(302, ['Location' => 'https://example.com/a'], ''),
            new Response(302, ['Location' => 'https://example.com/b'], ''),
        ], $this->publicRecords());
        $this->expectException(SubscriptionFetchException::class);
        $this->expectExceptionMessage('too many redirects');
        $fetcher->fetch('https://example.com/sub');
    }
}
