<?php
namespace Alzaf\BdCourier\Providers;

use Alzaf\BdCourier\Supports\CourierFraudCheckerSupport;
use Illuminate\Support\ServiceProvider;

class CourierFraudCheckerProvider extends ServiceProvider
{
    public function boot()
    {
        $configPath = __DIR__ . '/../../config/courier-fraud-checker.php';

        // Publish the config file on vendor:publish
        $this->publishes([
            $configPath => config_path('courier-fraud-checker.php'),
        ], 'config');
    }

    public function register()
    {
        $configPath = __DIR__ . '/../../config/courier-fraud-checker.php';

        $this->mergeConfigFrom(
            $configPath, 'courier-fraud-checker'
        );

        $this->app->singleton('courier-fraud-checker', function ($app) {
            return $app->make(CourierFraudCheckerSupport::class);
        });
    }
}
