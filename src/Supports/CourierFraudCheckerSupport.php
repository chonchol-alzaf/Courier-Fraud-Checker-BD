<?php

namespace Alzaf\BdCourier\Supports;

use Alzaf\BdCourier\Contracts\FraudCheckServiceInterface;
use Alzaf\BdCourier\Services\FraudCheck\CarryBeeService;
use Alzaf\BdCourier\Services\FraudCheck\PathaoService;
use Alzaf\BdCourier\Services\FraudCheck\RedxService;
use Alzaf\BdCourier\Services\FraudCheck\SteadfastService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourierFraudCheckerSupport
{
    private const SERVICE_MAP = [
        'steadfast' => SteadfastService::class,
        'pathao' => PathaoService::class,
        'redx' => RedxService::class,
        'carrybee' => CarryBeeService::class,
    ];

    public function __construct(protected Container $container) {}

    public function check(string $phoneNumber, bool $is_disable_cache = true): array
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $data = [];

        foreach ($this->enabledServices() as $service => $serviceClass) {
            $data[$service] = $this->safeServiceCall($service, $serviceClass, $phoneNumber, $is_disable_cache);
        }

        $data['totalSummary'] = $this->buildTotalSummary($data);

        return $data;
    }

    protected function enabledServices(): array
    {
        return array_filter(
            self::SERVICE_MAP,
            fn (string $service) => (bool) config("bd-courier.{$service}.enable"),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function buildTotalSummary(array $data): array
    {
        $success = 0;
        $cancel = 0;

        foreach ($data as $item) {
            if (
                isset($item['success'], $item['cancel'], $item['total'])
                && is_numeric($item['success'])
                && is_numeric($item['cancel'])
                && is_numeric($item['total'])
            ) {
                $success += (int) $item['success'];
                $cancel += (int) $item['cancel'];
            }
        }

        return DeliveryStatsCalculator::calculate($success, $cancel);
    }

    protected function safeServiceCall(string $service, string $serviceClass, string $phoneNumber, bool $is_disable_cache): array
    {
        $cacheKey = "courier:{$service}:phone:{$phoneNumber}";
        $shouldUseCache = ! $is_disable_cache;

        $fetchStats = function () use ($serviceClass, $phoneNumber): array {
            $service = $this->container->make($serviceClass);

            if (! $service instanceof FraudCheckServiceInterface) {
                throw new \UnexpectedValueException("Service [{$serviceClass}] must implement FraudCheckServiceInterface");
            }

            return $service->getCustomerDeliveryStats($phoneNumber);
        };

        try {
            if (! $shouldUseCache) {
                return $fetchStats();
            }

            $cachedResult = Cache::get($cacheKey);
            if (is_array($cachedResult)) {
                return $cachedResult;
            }

            if ($cachedResult !== null) {
                Cache::forget($cacheKey);
            }

            $result = $fetchStats();

            if (! is_array($result)) {
                throw new \UnexpectedValueException("Invalid response format from {$service}");
            }

            // Skip caching transient upstream errors.
            if (isset($result['error'])) {
                return $result;
            }

            Cache::put($cacheKey, $result, $this->resolveServiceCacheTtl($result));

            return $result;

        } catch (\Throwable $exception) {
            Log::warning('Courier service request failed', [
                'service' => $service,
                'exception' => $exception->getMessage(),
                'type' => $exception::class,
            ]);

            return [
                'error' => 'Unable to fetch data from this courier right now.',
            ];
        }
    }

    protected function resolveServiceCacheTtl(array $result): \DateTimeInterface
    {
        $total = data_get($result, 'total');
        if (! is_numeric($total)) {
            return now()->addMinutes(30);
        }

        $total = (int) $total;
        if ($total <= 0) {
            return now()->addMinutes(15);
        }

        if ($total < 5) {
            return now()->addHour();
        }

        if ($total < 20) {
            return now()->addHours(10);
        }

        return now()->addHours(20);
    }
}
