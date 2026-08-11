<?php

namespace App\Services\ThirdParty\Parsers;

use App\Services\ThirdParty\TemporaryNode;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ClashYamlParser implements SubscriptionParserInterface
{
    public function supports(string $content, ?string $contentType = null): bool
    {
        if (strpos($content, 'proxies:') === false) {
            return false;
        }
        try {
            $config = Yaml::parse($content);
        } catch (ParseException $e) {
            return false;
        }
        return is_array($config) && !empty($config['proxies']) && is_array($config['proxies']);
    }

    public function parse(string $content): array
    {
        try {
            $config = Yaml::parse($content);
        } catch (ParseException $e) {
            return [];
        }
        if (!is_array($config) || empty($config['proxies']) || !is_array($config['proxies'])) {
            return [];
        }
        $nodes = [];
        foreach ($config['proxies'] as $proxy) {
            if (!is_array($proxy)) {
                continue;
            }
            try {
                $node = $this->convertProxy($proxy);
                if ($node !== null) {
                    $nodes[] = $node;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $nodes;
    }

    private function convertProxy(array $proxy): ?TemporaryNode
    {
        $type = strtolower((string)($proxy['type'] ?? ''));
        $server = isset($proxy['server']) ? (string)$proxy['server'] : null;
        $port = isset($proxy['port']) ? (int)$proxy['port'] : null;
        $name = (string)($proxy['name'] ?? '');
        if (!$server || !$port) {
            return null;
        }
        $settings = [];

        switch ($type) {
            case 'ss':
            case 'shadowsocks':
                $settings = [
                    'credential' => (string)($proxy['password'] ?? ''),
                    'method' => (string)($proxy['cipher'] ?? ''),
                    'obfs' => (string)($proxy['plugin-opts']['mode'] ?? ''),
                    'obfs_host' => (string)($proxy['plugin-opts']['host'] ?? ''),
                    'obfs_path' => (string)($proxy['plugin-opts']['path'] ?? ''),
                ];
                if (empty($settings['obfs']) && isset($proxy['plugin'])) {
                    $settings['obfs'] = (string)$proxy['plugin'];
                }
                return new TemporaryNode('shadowsocks', $name, $server, $port, $settings, []);
            case 'vmess':
                $settings = [
                    'credential' => (string)($proxy['uuid'] ?? ''),
                    'network' => (string)($proxy['network'] ?? 'tcp'),
                    'tls' => !empty($proxy['tls']) ? 'tls' : '',
                    'sni' => (string)($proxy['servername'] ?? ''),
                    'host' => (string)($proxy['ws-opts']['headers']['Host'] ?? ($proxy['http-opts']['headers']['Host'][0] ?? '')),
                    'path' => (string)($proxy['ws-opts']['path'] ?? ($proxy['http-opts']['path'][0] ?? '')),
                    'serviceName' => (string)($proxy['grpc-opts']['grpc-service-name'] ?? ''),
                    'fp' => (string)($proxy['client-fingerprint'] ?? ''),
                    'allowInsecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'security' => (string)($proxy['cipher'] ?? 'auto'),
                    'mode' => (string)($proxy['xhttp-opts']['mode'] ?? ''),
                ];
                if (!empty($proxy['tls']) && $settings['host'] === '') {
                    $settings['host'] = $settings['sni'];
                }
                return new TemporaryNode('vmess', $name, $server, $port, $settings, []);
            case 'vless':
                $settings = [
                    'credential' => (string)($proxy['uuid'] ?? ''),
                    'network' => (string)($proxy['network'] ?? 'tcp'),
                    'security' => !empty($proxy['tls']) ? 'tls' : 'none',
                    'sni' => (string)($proxy['servername'] ?? ''),
                    'host' => (string)($proxy['ws-opts']['headers']['Host'] ?? ($proxy['http-opts']['headers']['Host'][0] ?? '')),
                    'path' => (string)($proxy['ws-opts']['path'] ?? ($proxy['http-opts']['path'][0] ?? '')),
                    'serviceName' => (string)($proxy['grpc-opts']['grpc-service-name'] ?? ''),
                    'headerType' => (string)($proxy['http-opts']['path'][0] ?? ($proxy['ws-opts']['path'] ?? '')),
                    'fp' => (string)($proxy['client-fingerprint'] ?? 'chrome'),
                    'insecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'pbk' => (string)($proxy['reality-opts']['public-key'] ?? ''),
                    'sid' => (string)($proxy['reality-opts']['short-id'] ?? ''),
                    'flow' => (string)($proxy['flow'] ?? ''),
                    'mode' => (string)($proxy['xhttp-opts']['mode'] ?? ''),
                ];
                if (!empty($proxy['tls']) && $settings['host'] === '') {
                    $settings['host'] = $settings['sni'];
                }
                return new TemporaryNode('vless', $name, $server, $port, $settings, []);
            case 'trojan':
                $settings = [
                    'credential' => (string)($proxy['password'] ?? ''),
                    'network' => (string)($proxy['network'] ?? 'tcp'),
                    'sni' => (string)($proxy['sni'] ?? ''),
                    'allowInsecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'fp' => (string)($proxy['client-fingerprint'] ?? ''),
                    'host' => (string)($proxy['ws-opts']['headers']['Host'] ?? ''),
                    'path' => (string)($proxy['ws-opts']['path'] ?? ''),
                    'serviceName' => (string)($proxy['grpc-opts']['grpc-service-name'] ?? ''),
                    'alpn' => (string)($proxy['alpn'][0] ?? ''),
                ];
                return new TemporaryNode('trojan', $name, $server, $port, $settings, []);
            case 'hysteria':
                $settings = [
                    'credential' => (string)($proxy['auth_str'] ?? ''),
                    'insecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'peer' => (string)($proxy['sni'] ?? ''),
                    'up_mbps' => (string)($proxy['up'] ?? ''),
                    'down_mbps' => (string)($proxy['down'] ?? ''),
                    'obfs' => (string)($proxy['obfs'] ?? ''),
                    'ports' => (string)($proxy['ports'] ?? ''),
                ];
                return new TemporaryNode('hysteria', $name, $server, $port, $settings, []);
            case 'hysteria2':
                $settings = [
                    'credential' => (string)($proxy['password'] ?? ''),
                    'insecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'sni' => (string)($proxy['sni'] ?? ''),
                    'obfs' => (string)($proxy['obfs'] ?? ''),
                    'obfs_password' => (string)($proxy['obfs-password'] ?? ''),
                    'ports' => (string)($proxy['ports'] ?? ''),
                ];
                return new TemporaryNode('hysteria2', $name, $server, $port, $settings, []);
            case 'tuic':
                $settings = [
                    'credential' => (string)($proxy['password'] ?? ($proxy['uuid'] ?? '')),
                    'sni' => (string)($proxy['sni'] ?? ''),
                    'insecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'congestion_control' => (string)($proxy['congestion-controller'] ?? 'cubic'),
                    'udp_relay_mode' => (string)($proxy['udp-relay-mode'] ?? 'native'),
                    'disable_sni' => !empty($proxy['disable-sni']) ? 1 : 0,
                    'alpn' => (string)($proxy['alpn'][0] ?? ''),
                ];
                return new TemporaryNode('tuic', $name, $server, $port, $settings, []);
            case 'anytls':
                $settings = [
                    'credential' => (string)($proxy['password'] ?? ''),
                    'network' => 'tcp',
                    'sni' => (string)($proxy['sni'] ?? ''),
                    'insecure' => !empty($proxy['skip-cert-verify']) ? 1 : 0,
                    'fp' => (string)($proxy['client-fingerprint'] ?? 'chrome'),
                    'alpn' => implode(',', (array)($proxy['alpn'] ?? [])),
                ];
                return new TemporaryNode('anytls', $name, $server, $port, $settings, []);
            default:
                return null;
        }
    }
}
