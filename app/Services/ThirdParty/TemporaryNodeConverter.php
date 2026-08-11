<?php

namespace App\Services\ThirdParty;

/**
 * Converts a parsed TemporaryNode into the unified server-array structure that
 * V2Board's existing subscription generators consume. Third-party nodes keep
 * their own credentials (sub_uuid) and are tagged with their source so they can
 * be filtered and debugged.
 */
class TemporaryNodeConverter
{
    private const SORT_OFFSET = 1000000;

    public function convert(TemporaryNode $node, int $sourceId): ?array
    {
        switch ($node->type) {
            case 'vless':
                return $this->convertVless($node, $sourceId);
            case 'vmess':
                return $this->convertVmess($node, $sourceId);
            case 'trojan':
                return $this->convertTrojan($node, $sourceId);
            case 'shadowsocks':
                return $this->convertShadowsocks($node, $sourceId);
            case 'hysteria':
                return $this->convertHysteria($node, $sourceId, 1);
            case 'hysteria2':
                return $this->convertHysteria($node, $sourceId, 2);
            case 'tuic':
                return $this->convertTuic($node, $sourceId);
            case 'anytls':
                return $this->convertAnyTLS($node, $sourceId);
            default:
                return null;
        }
    }

    public function fingerprint(TemporaryNode $node): string
    {
        $key = [
            $node->type,
            $node->server,
            $node->port,
        ];
        $settings = $node->settings;
        unset($settings['credential'], $settings['name']);
        foreach ([
            'method', 'network', 'security', 'tls', 'sni', 'host', 'path',
            'serviceName', 'flow', 'mode', 'insecure', 'obfs', 'obfs_host',
            'obfs_path', 'pbk', 'sid', 'peer', 'headerType', 'seed',
        ] as $field) {
            if (isset($settings[$field])) {
                $key[] = $settings[$field];
            }
        }
        return md5(json_encode($key));
    }

    private function base(TemporaryNode $node, int $sourceId, int $index): array
    {
        return [
            'type' => $node->type,
            'name' => $node->name,
            'host' => $node->server,
            'port' => $node->port,
            'sort' => self::SORT_OFFSET + $index,
            'id' => "tp-{$sourceId}-{$index}",
            'updated_at' => time(),
            'last_check_at' => time(),
            'show' => true,
            'group_id' => [],
            'source_type' => 'third_party',
            'source_id' => $sourceId,
            'is_third_party' => true,
        ];
    }

    private function convertVless(TemporaryNode $node, int $sourceId): ?array
    {
        $settings = $node->settings;
        $security = (string)($settings['security'] ?? '');
        $tls = $security === 'reality' ? 2 : ($security === 'tls' ? 1 : 0);

        $array = $this->base($node, $sourceId, $node->port);
        $array['network'] = (string)($settings['network'] ?? 'tcp');
        $array['tls'] = $tls;
        $array['flow'] = (string)($settings['flow'] ?? '');
        $array['tls_settings'] = [
            'server_name' => (string)($settings['sni'] ?? ''),
            'fingerprint' => (string)($settings['fp'] ?? 'chrome'),
            'allow_insecure' => (int)($settings['insecure'] ?? 0),
            'public_key' => (string)($settings['pbk'] ?? ''),
            'short_id' => (string)($settings['sid'] ?? ''),
            'ech' => (string)($settings['ech'] ?? ''),
            'ech_config' => (string)($settings['ech_config'] ?? ''),
        ];
        $array['network_settings'] = $this->mapNetworkSettings($settings);
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function convertVmess(TemporaryNode $node, int $sourceId): ?array
    {
        $settings = $node->settings;
        $array = $this->base($node, $sourceId, $node->port);
        $array['network'] = (string)($settings['network'] ?? 'tcp');
        $array['tls'] = ((string)($settings['tls'] ?? '') === 'tls') ? 1 : 0;
        $array['tls_settings'] = [
            'server_name' => (string)($settings['sni'] ?? ''),
            'allow_insecure' => (int)($settings['allowInsecure'] ?? 0),
            'fingerprint' => (string)($settings['fp'] ?? 'chrome'),
        ];
        $array['network_settings'] = $this->mapNetworkSettings($settings);
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function convertTrojan(TemporaryNode $node, int $sourceId): ?array
    {
        $settings = $node->settings;
        $array = $this->base($node, $sourceId, $node->port);
        $array['network'] = (string)($settings['network'] ?? 'tcp');
        $array['network_settings'] = $this->mapNetworkSettings($settings);
        $sni = (string)($settings['sni'] ?? '');
        $allowInsecure = (int)($settings['allowInsecure'] ?? 0);
        $array['server_name'] = $sni;
        $array['allow_insecure'] = $allowInsecure;
        $array['tls_settings'] = [
            'server_name' => $sni,
            'allow_insecure' => $allowInsecure,
            'fingerprint' => (string)($settings['fp'] ?? 'chrome'),
        ];
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function convertShadowsocks(TemporaryNode $node, int $sourceId): ?array
    {
        $settings = $node->settings;
        $array = $this->base($node, $sourceId, $node->port);
        $array['cipher'] = (string)($settings['method'] ?? '');
        $array['obfs'] = (string)($settings['obfs'] ?? '');
        $array['obfs-host'] = (string)($settings['obfs_host'] ?? '');
        $array['obfs-path'] = (string)($settings['obfs_path'] ?? '');
        $array['created_at'] = time();
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function convertHysteria(TemporaryNode $node, int $sourceId, int $version): ?array
    {
        $settings = $node->settings;
        $array = $this->base($node, $sourceId, $node->port);
        $array['version'] = $version;
        $array['insecure'] = (int)($settings['insecure'] ?? 0);
        $array['server_name'] = (string)($settings['peer'] ?? ($settings['sni'] ?? ''));
        $array['up_mbps'] = (string)($settings['upmbps'] ?? ($settings['up'] ?? ''));
        $array['down_mbps'] = (string)($settings['downmbps'] ?? ($settings['down'] ?? ''));
        $array['obfs'] = (string)($settings['obfs'] ?? '');
        $array['obfs_password'] = (string)($settings['obfsParam'] ?? ($settings['obfs_password'] ?? ''));
        if (isset($settings['ports']) && $settings['ports'] !== '') {
            $array['mport'] = (string)$settings['ports'];
        }
        $array['tls_settings'] = [
            'server_name' => (string)($settings['peer'] ?? ($settings['sni'] ?? '')),
            'allow_insecure' => (int)($settings['insecure'] ?? 0),
        ];
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function convertTuic(TemporaryNode $node, int $sourceId): ?array
    {
        $settings = $node->settings;
        $array = $this->base($node, $sourceId, $node->port);
        $array['congestion_control'] = (string)($settings['congestion_control'] ?? 'cubic');
        $array['insecure'] = (int)($settings['insecure'] ?? 0);
        $array['disable_sni'] = (int)($settings['disable_sni'] ?? 0);
        $array['udp_relay_mode'] = (string)($settings['udp_relay_mode'] ?? 'native');
        $array['zero_rtt_handshake'] = (int)($settings['zero_rtt_handshake'] ?? 0);
        $array['server_name'] = (string)($settings['sni'] ?? '');
        $array['tls_settings'] = [
            'server_name' => (string)($settings['sni'] ?? ''),
            'allow_insecure' => (int)($settings['insecure'] ?? 0),
        ];
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function convertAnyTLS(TemporaryNode $node, int $sourceId): ?array
    {
        $settings = $node->settings;
        $array = $this->base($node, $sourceId, $node->port);
        $array['network'] = (string)($settings['network'] ?? 'tcp');
        $array['insecure'] = (int)($settings['insecure'] ?? 0);
        $array['server_name'] = (string)($settings['sni'] ?? '');
        $array['tls'] = ((string)($settings['security'] ?? '') === 'reality') ? 2 : 0;
        $array['tls_settings'] = [
            'server_name' => (string)($settings['sni'] ?? ''),
            'allow_insecure' => (int)($settings['insecure'] ?? 0),
            'fingerprint' => (string)($settings['fp'] ?? 'chrome'),
            'public_key' => (string)($settings['pbk'] ?? ''),
            'short_id' => (string)($settings['sid'] ?? ''),
        ];
        $array['sub_uuid'] = (string)($settings['credential'] ?? '');
        return $array;
    }

    private function mapNetworkSettings(array $settings): array
    {
        $network = (string)($settings['network'] ?? 'tcp');
        switch ($network) {
            case 'ws':
                return [
                    'path' => (string)($settings['path'] ?? ''),
                    'headers' => ['Host' => (string)($settings['host'] ?? '')],
                ];
            case 'grpc':
                return [
                    'serviceName' => (string)($settings['serviceName'] ?? ''),
                ];
            case 'tcp':
                if ((string)($settings['headerType'] ?? '') === 'http' || (string)($settings['type'] ?? '') === 'http') {
                    return [
                        'header' => [
                            'type' => 'http',
                            'request' => [
                                'headers' => ['Host' => [(string)($settings['host'] ?? '')]],
                                'path' => [(string)($settings['path'] ?? '')],
                            ],
                        ],
                    ];
                }
                return [];
            case 'httpupgrade':
                return [
                    'path' => (string)($settings['path'] ?? ''),
                    'host' => (string)($settings['host'] ?? ''),
                ];
            case 'xhttp':
                return [
                    'path' => (string)($settings['path'] ?? ''),
                    'host' => (string)($settings['host'] ?? ''),
                    'mode' => (string)($settings['mode'] ?? 'auto'),
                ];
            case 'kcp':
                return [
                    'header' => ['type' => (string)($settings['headerType'] ?? 'none')],
                    'seed' => (string)($settings['seed'] ?? ''),
                ];
            default:
                return [];
        }
    }
}
