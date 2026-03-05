<?php
namespace Alzaf\CourierFraudCheckerBd\Providers;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerSupport;
use Illuminate\Support\ServiceProvider;

class CourierFraudCheckerProvider extends ServiceProvider
{
    public function boot()
    {
        $configPath = __DIR__ . '/../../config/courier-fraud-checker-bd.php';

        // Publish the config file on vendor:publish
        $this->publishes([
            $configPath => config_path('courier-fraud-checker-bd.php'),
        ], 'config');
    }

    public function register()
    {
        $configPath = __DIR__ . '/../../config/courier-fraud-checker-bd.php';

        $this->mergeConfigFrom(
            $configPath, 'courier-fraud-checker-bd'
        );

        $this->app->singleton('courier-fraud-checker', function ($app) {
            return $app->make(CourierFraudCheckerSupport::class);
        });
    }
}
