<?php

namespace Alzaf\BdCourier\Providers;

use Alzaf\BdCourier\Supports\CourierFraudCheckerSupport;
use Alzaf\BdCourier\Supports\CourierParcelSupport;
use Illuminate\Support\ServiceProvider;

class CourierServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $configPath = __DIR__.'/../../config/bd-courier.php';

        // Publish the config file on vendor:publish
        $this->publishes([
            $configPath => config_path('bd-courier.php'),
        ], 'config');
    }

    public function register()
    {
        $configPath = __DIR__.'/../../config/bd-courier.php';

        $this->mergeConfigFrom(
            $configPath, 'bd-courier'
        );

        $this->app->singleton('courier-fraud-checker', function ($app) {
            return $app->make(CourierFraudCheckerSupport::class);
        });

        $this->app->singleton('courier-parcel', function ($app) {
            return $app->make(CourierParcelSupport::class);
        });
    }
}
