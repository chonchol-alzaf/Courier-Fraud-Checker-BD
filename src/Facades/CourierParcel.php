<?php
namespace Alzaf\BdCourier\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array check($phoneNumber)
 */
class CourierParcel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return "courier-parcel";
    }
}
