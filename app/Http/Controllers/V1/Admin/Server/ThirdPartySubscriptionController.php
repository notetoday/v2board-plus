<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ThirdPartySubscriptionSave;
use App\Models\ThirdPartySubscription;
use App\Services\ThirdParty\ThirdPartySubscriptionService;
use Illuminate\Http\Request;

class ThirdPartySubscriptionController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $source = ThirdPartySubscription::find((int)$request->input('id'));
            return response([
                'data' => $source ? $this->decorate($source) : []
            ]);
        }
        $sources = ThirdPartySubscription::orderBy('sort', 'ASC')->get();
        $data = [];
        foreach ($sources as $source) {
            $data[] = $this->decorate($source);
        }
        return response([
            'data' => $data
        ]);
    }

    public function save(ThirdPartySubscriptionSave $request)
    {
        $params = $request->validated();
        if ($request->input('id')) {
            $source = ThirdPartySubscription::find((int)$request->input('id'));
            if (!$source) {
                abort(500, '订阅源不存在');
            }
            try {
                $source->update($params);
            } catch (\Exception $e) {
                abort(500, '保存失败');
            }
            return response([
                'data' => true
            ]);
        }

        if (!ThirdPartySubscription::create($params)) {
            abort(500, '创建失败');
        }
        return response([
            'data' => true
        ]);
    }

    public function update(Request $request)
    {
        if (!$request->input('id')) {
            abort(500, '参数错误');
        }
        $source = ThirdPartySubscription::find((int)$request->input('id'));
        if (!$source) {
            abort(500, '订阅源不存在');
        }
        $params = $request->only(['enabled', 'name', 'url', 'sort', 'update_interval']);
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            }
        }
        try {
            $source->update($params);
        } catch (\Exception $e) {
            abort(500, '保存失败');
        }
        if (isset($params['enabled']) && !(int)$params['enabled']) {
            $service = new ThirdPartySubscriptionService();
            $service->clearCache((int)$source->id);
        }
        return response([
            'data' => true
        ]);
    }

    public function drop(Request $request)
    {
        if (!$request->input('id')) {
            abort(500, '参数错误');
        }
        $source = ThirdPartySubscription::find((int)$request->input('id'));
        if (!$source) {
            abort(500, '订阅源不存在');
        }
        $service = new ThirdPartySubscriptionService();
        $service->clearCache((int)$source->id);
        return response([
            'data' => $source->delete()
        ]);
    }

    public function sync(Request $request)
    {
        $service = new ThirdPartySubscriptionService();
        if ($request->input('id')) {
            $source = ThirdPartySubscription::find((int)$request->input('id'));
            if (!$source) {
                abort(500, '订阅源不存在');
            }
            $result = $service->sync($source);
            return response([
                'data' => [
                    'id' => (int)$source->id,
                    'success' => $result['success'],
                    'node_count' => $result['node_count'],
                    'error' => $result['error'],
                    'fetched_at' => $service->getCacheFetchedAt((int)$source->id),
                ]
            ]);
        }

        $results = [];
        foreach (ThirdPartySubscription::where('enabled', 1)->get() as $source) {
            $result = $service->sync($source);
            $results[] = [
                'id' => (int)$source->id,
                'name' => $source->name,
                'success' => $result['success'],
                'node_count' => $result['node_count'],
                'error' => $result['error'],
            ];
        }
        return response([
            'data' => $results
        ]);
    }

    public function status(Request $request)
    {
        if (!$request->input('id')) {
            abort(500, '参数错误');
        }
        $source = ThirdPartySubscription::find((int)$request->input('id'));
        if (!$source) {
            abort(500, '订阅源不存在');
        }
        return response([
            'data' => $this->decorate($source)
        ]);
    }

    private function decorate(ThirdPartySubscription $source): array
    {
        $service = new ThirdPartySubscriptionService();
        $fetchedAt = $service->getCacheFetchedAt((int)$source->id);
        $nodeCount = $service->getCachedNodeCount((int)$source->id);
        return array_merge($source->toArray(), [
            'node_count' => $nodeCount,
            'cache_exists' => $fetchedAt !== null,
            'fetched_at' => $fetchedAt,
            'cached' => $fetchedAt !== null,
        ]);
    }
}
