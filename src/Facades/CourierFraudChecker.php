<?php
namespace Alzaf\CourierFraudChecker\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array check($phoneNumber)
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
        return "courier-fraud-checker";
    }
}
