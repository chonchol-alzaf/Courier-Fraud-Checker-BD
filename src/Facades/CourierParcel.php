<?php

namespace Alzaf\BdCourier\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed call(string $courier, string $action, mixed ...$args)
 * @method static mixed storeCreate(string $courier_name, \App\Models\PickupPoint $pickup_points)
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
        return 'courier-parcel';
    }
}
