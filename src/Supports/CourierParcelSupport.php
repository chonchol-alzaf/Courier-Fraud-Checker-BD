<?php
namespace Alzaf\BdCourier\Supports;

use Alzaf\BdCourier\Services\CarryBeeService;
use Alzaf\BdCourier\Services\PathaoService;
use Alzaf\BdCourier\Services\RedxService;
use Alzaf\BdCourier\Services\SteadfastService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class CourierParcelSupport
{
    public function __construct(protected Container $container)
    {}

    public function add(): array
    {
        $defaultParcel = strtolower((string) config('bd-courier.default_parcel', ''));

        $services = [
            'pathao'    => PathaoService::class,
            'redx'      => RedxService::class,
            'steadfast' => SteadfastService::class,
            'carrybee'  => CarryBeeService::class,
        ];

        $serviceClass = $services[$defaultParcel] ?? null;
        if (! $serviceClass) {
            return [
                'error' => "Unsupported default parcel [{$defaultParcel}]",
            ];
        }

        try {
            $result = $this->container->make($serviceClass)->add();

            return is_array($result) ? $result : [];
        } catch (\Throwable $exception) {
            Log::warning('Courier parcel add request failed', [
                'default_parcel' => $defaultParcel,
                'exception'      => $exception->getMessage(),
                'type'           => $exception::class,
            ]);

            return [
                'error' => 'Unable to add parcel right now.',
            ];
        }
    }
}
