<?php
namespace Alzaf\BdCourier\Supports;

use Alzaf\BdCourier\Services\PathaoService;
use Alzaf\BdCourier\Services\RedxService;
use Alzaf\BdCourier\Services\SteadfastService;
use Alzaf\CourierFraudChecker\Services\CarryBeeService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourierParcelSupport
{
    public function __construct(protected Container $container)
    {}

    public function check(string $phoneNumber, bool $is_disable_cache = true): array
    {

        $data = [];
        if (config('bd-courier.steadfast.enable')) {
            $data['steadfast'] = $this->safeServiceCall('steadfast', SteadfastService::class, $phoneNumber, $is_disable_cache);
        }
        if (config('bd-courier.pathao.enable')) {
            $data['pathao'] = $this->safeServiceCall('pathao', PathaoService::class, $phoneNumber, $is_disable_cache);
        }
        if (config('bd-courier.redx.enable')) {
            $data['redx'] = $this->safeServiceCall('redx', RedxService::class, $phoneNumber, $is_disable_cache);
        }
        if (config('bd-courier.carrybee.enable')) {
            $data['carrybee'] = $this->safeServiceCall('carrybee', CarryBeeService::class, $phoneNumber, $is_disable_cache);
        }

        $total   = 0;
        $success = 0;
        $cancel  = 0;

        foreach ($data as $item) {
            if (isset($item['total']) && is_numeric($item['total'])) {
                $total += (int) $item['total'];
            }
            if (isset($item['success']) && is_numeric($item['success'])) {
                $success += (int) $item['success'];
            }
            if (isset($item['cancel']) && is_numeric($item['cancel'])) {
                $cancel += (int) $item['cancel'];
            }
        }

        $successRate = $total > 0
            ? round(($success / $total) * 100, 2)
            : null;

        $cancelRate = $total > 0
            ? round(($cancel / $total) * 100, 2)
            : null;

        $data['totalSummary'] = [
            'total'        => $total,
            'success'      => $success,
            'cancel'       => $cancel,
            'success_rate' => $successRate,
            'cancel_rate'  => $cancelRate,
        ];

        return $data;
    }

    protected function safeServiceCall(string $service, string $serviceClass, string $phoneNumber, bool $is_disable_cache): array
    {
        $cacheKey       = "courier:{$service}:phone:{$phoneNumber}";
        $shouldUseCache = ! $is_disable_cache;

        $fetchStats = function () use ($serviceClass, $phoneNumber): array {
            return $this->container
                ->make($serviceClass)
                ->getCustomerDeliveryStats($phoneNumber);
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
                'service'   => $service,
                'exception' => $exception->getMessage(),
                'type'      => $exception::class,
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
