<?php

namespace App\Providers;

use App\Seo\DataForSeoProvider;
use App\Seo\MockResponses;
use App\Seo\Operation;
use App\Seo\Provider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Provider::class, function () {
            if (config('seo.mode') === 'mock') {
                $http = new Factory;
                $http->preventStrayRequests();
                $stubs = [];
                foreach (Operation::cases() as $operation) {
                    $stubs[$operation->url()] = fn ($request) => Factory::response(MockResponses::forRequest($request));
                }
                $http->fake($stubs);
            } else {
                $http = Http::getFacadeRoot();
            }

            return new DataForSeoProvider($http);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('seo-jobs', fn ($job) => Limit::perMinute(10)->by('seo-worker'));
    }
}
