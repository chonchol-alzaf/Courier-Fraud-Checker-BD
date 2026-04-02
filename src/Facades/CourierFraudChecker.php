<?php

namespace Alzaf\BdCourier\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array check(string $phoneNumber, bool $is_disable_cache = true)
 */
class CourierFraudChecker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'courier-fraud-checker';
    }
}
