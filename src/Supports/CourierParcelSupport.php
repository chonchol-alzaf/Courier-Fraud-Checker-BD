<?php
namespace Alzaf\BdCourier\Supports;

use Alzaf\BdCourier\Enums\CourierEnum;
use Alzaf\BdCourier\Services\Parcel\CarryBeeService;
use Alzaf\BdCourier\Services\Parcel\PathaoService;
use Alzaf\BdCourier\Services\Parcel\RedxService;
use Alzaf\BdCourier\Services\Parcel\SteadfastService;
use App\Models\PickupPoint;
use Illuminate\Contracts\Container\Container;

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

        if (! method_exists($service, $action)) {
            return ['error' => "Method [{$action}] not supported for [{$courier}]"];
        }

        return $service->{$action}(...$args);
    }

    public function storeCreate($courier_name, PickupPoint $pickup_points)
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
