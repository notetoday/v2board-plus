<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThirdPartySubscriptionsTable extends Migration
{
    public function up()
    {
        Schema::create('third_party_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255)->default('');
            $table->text('url');
            $table->unsignedTinyInteger('enabled')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('update_interval')->default(60);
            $table->integer('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('created_at')->default(0);
            $table->integer('updated_at')->default(0);
        });
    }

    public function down()
    {
        Schema::dropIfExists('third_party_subscriptions');
    }
}
