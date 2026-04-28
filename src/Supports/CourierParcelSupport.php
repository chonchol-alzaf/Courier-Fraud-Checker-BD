<?php

namespace Alzaf\BdCourier\Supports;

use Alzaf\BdCourier\Contracts\ParcelServiceInterface;
use Alzaf\BdCourier\Enums\CourierEnum;
use Alzaf\BdCourier\Services\Parcel\CarryBeeService;
use Alzaf\BdCourier\Services\Parcel\PathaoService;
use Alzaf\BdCourier\Services\Parcel\RedxService;
use Alzaf\BdCourier\Services\Parcel\SteadfastService;
use App\Models\PickupPoint;
use Illuminate\Contracts\Container\Container;

class CourierParcelSupport
{
    private const SERVICE_MAP = [
        CourierEnum::PATHAO->value => PathaoService::class,
        CourierEnum::REDX->value => RedxService::class,
        CourierEnum::STEADFAST->value => SteadfastService::class,
        CourierEnum::CARRYBEE->value => CarryBeeService::class,
    ];

    private array $services;

    public function __construct(protected Container $container)
    {
        $this->services = self::SERVICE_MAP;
    }

    public function call(string $courier, string $action, mixed ...$args): mixed
    {
        $courier = strtolower($courier);
        $serviceClass = $this->services[$courier] ?? null;
        if (! $serviceClass) {
            return ['error' => "Unsupported courier [{$courier}]"];
        }

        if (! (bool) config("bd-courier.{$courier}.parcel_enable")) {
            return ['error' => "Parcel service [{$courier}] is disabled"];
        }

        $service = $this->container->make($serviceClass);

        if (! $service instanceof ParcelServiceInterface) {
            return ['error' => "Service [{$courier}] does not implement the parcel contract"];
        }

        if (! method_exists($service, $action)) {
            return ['error' => "Method [{$action}] not supported for [{$courier}]"];
        }

        return $service->{$action}(...$args);
    }

    public function storeCreate(string $courier_name, PickupPoint $pickup_points): mixed
    {
        return $this->call($courier_name, 'storeCreate', $pickup_points);
    }
}
