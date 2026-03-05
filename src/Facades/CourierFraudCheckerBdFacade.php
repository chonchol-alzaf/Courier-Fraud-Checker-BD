<?php
namespace Alzaf\CourierFraudCheckerBd\Facades;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerBd;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array check($phoneNumber)
 */
class CourierFraudCheckerBdFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return CourierFraudCheckerBd::class;
    }
}
