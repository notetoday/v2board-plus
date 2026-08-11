<?php

namespace App\Providers;

use App\Services\ThirdParty\SubscriptionFetcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Prevent the container from auto-wiring a Guzzle client into the
        // fetcher. A container-resolved client would be treated as a custom
        // client and skip the CURLOPT_RESOLVE SSRF/DNS-rebinding pinning.
        $this->app->bind(SubscriptionFetcher::class, function () {
            return new SubscriptionFetcher();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['view']->addNamespace('theme', public_path() . '/theme');
    }
}
