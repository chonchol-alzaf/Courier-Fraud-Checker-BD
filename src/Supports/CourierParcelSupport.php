<?php
namespace Alzaf\BdCourier\Supports;

use Alzaf\BdCourier\Enums\CourierEnum;
use Alzaf\BdCourier\Services\Parcel\CarryBeeService;
use Alzaf\BdCourier\Services\Parcel\PathaoService;
use Alzaf\BdCourier\Services\Parcel\RedxService;
use Alzaf\BdCourier\Services\Parcel\SteadfastService;
use App\Models\PickupPoints;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class CourierParcelSupport
{
    private $services;
    public function __construct(protected Container $container)
    {
        $this->services = [
            CourierEnum::PATHAO->value    => PathaoService::class,
            CourierEnum::REDX->value      => RedxService::class,
            CourierEnum::STEADFAST->value => SteadfastService::class,
            CourierEnum::CARRYBEE->value  => CarryBeeService::class,
        ];
    }

    public function call(string $courier, string $action, ...$args)
    {
        $serviceClass = $this->services[$courier] ?? null;
        if (! $serviceClass) {
            return ['error' => "Unsupported courier [{$courier}]"];
        }

        $service = $this->container->make($serviceClass);
        $map     = [
            'createStore' => 'storeCreate',
            'cityList'        => 'cityList',
            'zoneList'        => 'zoneList',
            'areaList'        => 'areaList',
        ];
        $method = $map[$action] ?? $action;

        if (! method_exists($service, $method)) {
            return ['error' => "Method [{$method}] not supported for [{$courier}]"];
        }

        return $service->{$method}(...$args);
    }

    public function createStore($courier_name, PickupPoints $pickup_points)
    {
        $serviceClass = $this->services[$courier_name] ?? null;
        if (! $serviceClass) {
            return [
                'error' => "Unsupported  courier [{$courier_name}]",
            ];
        }

        return $this->container->make($serviceClass)->storeCreate($pickup_points);

    }
}
