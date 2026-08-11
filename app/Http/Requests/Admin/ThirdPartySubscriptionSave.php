<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ThirdPartySubscriptionSave extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:2048',
            'enabled' => 'nullable|integer|in:0,1',
            'sort' => 'nullable|integer',
            'update_interval' => 'nullable|integer|min:1|max:10080',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '订阅名称不能为空',
            'url.required' => '订阅地址不能为空',
            'enabled.in' => '启用状态格式不正确',
            'update_interval.min' => '更新间隔不能小于1分钟',
            'update_interval.max' => '更新间隔不能超过10080分钟',
        ];
    }
}
