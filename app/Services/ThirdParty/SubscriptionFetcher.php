<?php

namespace App\Services\ThirdParty;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Fetches third-party subscription content with strict network safety limits:
 * SSRF protection (private/link-local/metadata IPs and DNS rebinding),
 * bounded timeouts, bounded redirects, bounded body size and content-type
 * checks.
 *
 * Redirects are followed manually so that every hop is validated against the
 * same SSRF rules and the hostname-to-public-IP pinning is re-applied, which
 * also defeats DNS rebinding across redirect chains.
 */
class SubscriptionFetcher
{
    private const MAX_BODY_BYTES = 2097152;
    private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 15;
    private const MAX_REDIRECTS = 3;

    private const BLOCKED_CONTENT_TYPES = [
        'image/', 'audio/', 'video/', 'font/',
        'application/zip', 'application/gzip', 'application/x-tar',
        'application/x-7z-compressed', 'application/pdf',
        'application/x-executable', 'application/x-msdownload',
        'application/x-dosexec', 'application/vnd.',
    ];

    private Client $client;
    private $resolver;
    private bool $customClient;
    private int $maxBodyBytes;

    public function __construct(?Client $client = null, ?callable $resolver = null, ?int $maxBodyBytes = null)
    {
        $this->customClient = $client !== null;
        $this->client = $client ?? new Client();
        $this->resolver = $resolver ?? function (string $host): array {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            return $records === false ? [] : $records;
        };
        $this->maxBodyBytes = $maxBodyBytes ?? self::MAX_BODY_BYTES;
    }

    public function fetch(string $url): string
    {
        $currentUrl = $url;

        for ($redirects = 0; ; $redirects++) {
            $parts = $this->validateUrl($currentUrl);

            $host = (string)$parts['host'];
            $port = $this->portOf($parts);

            $resolve = $this->assertPublicHost($host, $port);

            $options = $this->baseOptions();
            if ($resolve !== null && !$this->customClient) {
                $options['curl'][CURLOPT_RESOLVE] = [$resolve];
                $options['handler'] = new CurlHandler();
            }

            try {
                $response = $this->client->request('GET', $currentUrl, $options);
            } catch (\Throwable $e) {
                throw new SubscriptionFetchException('request failed: ' . $e->getMessage());
            }

            if ($this->isRedirect($response)) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new SubscriptionFetchException('too many redirects');
                }
                $location = $response->getHeaderLine('Location');
                if ($location === '') {
                    throw new SubscriptionFetchException('redirect response without location header');
                }
                $currentUrl = (string)UriResolver::resolve(Utils::uriFor($url), Utils::uriFor($location));
                continue;
            }

            $status = $response->getStatusCode();
            if ($status !== 200) {
                throw new SubscriptionFetchException("unexpected http status: {$status}");
            }

            $contentType = $response->getHeaderLine('Content-Type');
            $this->assertContentType($contentType);

            return $this->readBody($response);
        }
    }

    private function isRedirect(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    private function baseOptions(): array
    {
        return [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::STREAM => true,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            RequestOptions::TIMEOUT => self::REQUEST_TIMEOUT,
            RequestOptions::VERIFY => true,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HEADERS => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept' => '*/*',
            ],
        ];
    }

    private function validateUrl(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new SubscriptionFetchException('invalid subscription url');
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new SubscriptionFetchException('only http/https urls are allowed');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new SubscriptionFetchException('userinfo in subscription url is not allowed');
        }
        return $parts;
    }

    private function portOf(array $parts): int
    {
        $default = (($parts['scheme'] ?? '') === 'https') ? 443 : 80;
        return isset($parts['port']) ? (int)$parts['port'] : $default;
    }

    /**
     * Returns the CURLOPT_RESOLVE entry pinning hostname to a validated public
     * IP, or null when the host is already a literal IP (which was validated).
     */
    private function assertPublicHost(string $host, int $port): ?string
    {
        $isIpv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isIpv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if ($isIpv4 || $isIpv6) {
            if (!$this->isPublicIp($host)) {
                throw new SubscriptionFetchException("subscription host is not reachable: {$host}");
            }
            return null;
        }

        $records = ($this->resolver)($host);
        if (empty($records)) {
            throw new SubscriptionFetchException('subscription hostname could not be resolved');
        }

        $ip = null;
        foreach ($records as $record) {
            $candidate = $record['ip'] ?? ($record['ipv6'] ?? null);
            if ($candidate === null) {
                continue;
            }
            if (!$this->isPublicIp($candidate)) {
                throw new SubscriptionFetchException("subscription hostname resolves to a non-public ip: {$host}");
            }
            if ($ip === null) {
                $ip = $candidate;
            }
        }
        if ($ip === null) {
            throw new SubscriptionFetchException('subscription hostname has no usable address');
        }

        return "{$host}:{$port}:{$ip}";
    }

    private function isPublicIp(string $ip): bool
    {
        $v4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if ($v4 !== false) {
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
            $long = ip2long($v4);
            if ($long === false) {
                return false;
            }
            $long = (float)sprintf('%u', $long);
            // 0.0.0.0/8, 100.64.0.0/10 (CGNAT), 127.0.0.0/8, 169.254.0.0/16,
            // 198.18.0.0/15 (benchmarking), 224.0.0.0/4 (multicast), 240.0.0.0/4 (reserved)
            $blocked = [
                [0x00000000, 0x00FFFFFF],
                [0x64400000, 0x647FFFFF],
                [0x7F000000, 0x7FFFFFFF],
                [0xA9FE0000, 0xA9FEFFFF],
                [0xC6120000, 0xC613FFFF],
                [0xE0000000, 0xEFFFFFFF],
                [0xF0000000, 0xFFFFFFFF],
            ];
            foreach ($blocked as [$start, $end]) {
                if ($long >= $start && $long <= $end) {
                    return false;
                }
            }
            return true;
        }

        $v6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($v6 !== false) {
            // Expand to be able to prefix-match private ranges.
            $packed = inet_pton($v6);
            if ($packed === false) {
                return false;
            }
            $hex = bin2hex($packed);
            if (strpos($hex, '00000000000000000000000000000001') === 0) return false; // ::1
            if (strpos($hex, 'fc') === 0 || strpos($hex, 'fd') === 0) return false; // fc00::/7
            if (strpos($hex, 'fe80') === 0 || strpos($hex, 'fe81') === 0
                || strpos($hex, 'fe82') === 0 || strpos($hex, 'fe83') === 0) return false; // fe80::/10
            if (strpos($hex, '00000000000000000000000000000000') === 0) return false; // ::
            // IPv4-mapped ::ffff:x.x.x.x
            if (strpos($hex, '00000000000000000000ffff') === 0) {
                $mapped = substr($hex, 24);
                return $this->isPublicIp(long2ip((int)hexdec($mapped)));
            }
            return true;
        }

        return false;
    }

    private function assertContentType(?string $contentType): void
    {
        if ($contentType === null || $contentType === '') {
            return;
        }
        $contentType = strtolower(trim(explode(';', $contentType)[0]));
        foreach (self::BLOCKED_CONTENT_TYPES as $blocked) {
            if (strpos($contentType, $blocked) === 0) {
                throw new SubscriptionFetchException("unexpected content-type: {$contentType}");
            }
        }
    }

    private function readBody($response): string
    {
        $body = $response->getBody();
        $content = '';
        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                break;
            }
            $content .= $chunk;
            if (strlen($content) > $this->maxBodyBytes) {
                throw new SubscriptionFetchException('response body exceeds size limit');
            }
        }
        return $content;
    }
}
