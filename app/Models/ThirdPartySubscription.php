<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartySubscription extends Model
{
    protected $table = 'third_party_subscriptions';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'enabled' => 'boolean',
        'last_sync_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
