<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class AppController extends Controller
{
    public function getConfig(Request $request)
    {
        $servers = [];
        $user = $request->user;
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
        }
        $defaultConfig = base_path() . '/resources/rules/app.clash.yaml';
        $customConfig = base_path() . '/resources/rules/custom.app.clash.yaml';
        if (File::exists($customConfig)) {
            $config = Yaml::parseFile($customConfig);
        } else {
            $config = Yaml::parseFile($defaultConfig);
        }
        $proxy = [];
        $proxies = [];

        foreach ($servers as $item) {
            if (($item['type'] ?? null) === 'v2node' && isset($item['protocol'])) {
                $item['type'] = $item['protocol'];
            }
            switch ($item['type']) {
                case 'shadowsocks':
                    array_push($proxy, \App\Protocols\Clash::buildShadowsocks(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'vmess':
                    array_push($proxy, \App\Protocols\Clash::buildVmess(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'vless':
                    array_push($proxy, \App\Protocols\Clash::buildVless(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'trojan':
                    array_push($proxy, \App\Protocols\Clash::buildTrojan(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'tuic':
                    array_push($proxy, \App\Protocols\Clash::buildTuic(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'anytls':
                    array_push($proxy, \App\Protocols\Clash::buildAnyTLS(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'hysteria':
                    array_push($proxy, \App\Protocols\Clash::buildHysteria(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
                case 'hysteria2':
                    array_push($proxy, \App\Protocols\Clash::buildHysteria2(\App\Utils\Helper::resolveServerCredential($user['uuid'], $item), $item));
                    array_push($proxies, $item['name']);
                    break;
            }
        }

        $config['proxies'] = array_merge($config['proxies'] ? $config['proxies'] : [], $proxy);
        foreach ($config['proxy-groups'] as $k => $v) {
            $config['proxy-groups'][$k]['proxies'] = array_merge($config['proxy-groups'][$k]['proxies'], $proxies);
        }
        $yamlContent = Yaml::dump($config);
        return response($yamlContent, 200)
            ->header('Content-Type', 'text/yaml');
    }

    public function getVersion(Request $request)
    {
        if (strpos($request->header('user-agent'), 'tidalab/4.0.0') !== false
            || strpos($request->header('user-agent'), 'tunnelab/4.0.0') !== false
        ) {
            if (strpos($request->header('user-agent'), 'Win64') !== false) {
                return response([
                    'data' => [
                        'version' => config('v2board.windows_version'),
                        'download_url' => config('v2board.windows_download_url')
                    ]
                ]);
            } else {
                return response([
                    'data' => [
                        'version' => config('v2board.macos_version'),
                        'download_url' => config('v2board.macos_download_url')
                    ]
                ]);
            }
            return;
        }
        return response([
            'data' => [
                'windows_version' => config('v2board.windows_version'),
                'windows_download_url' => config('v2board.windows_download_url'),
                'macos_version' => config('v2board.macos_version'),
                'macos_download_url' => config('v2board.macos_download_url'),
                'android_version' => config('v2board.android_version'),
                'android_download_url' => config('v2board.android_download_url')
            ]
        ]);
    }
}
