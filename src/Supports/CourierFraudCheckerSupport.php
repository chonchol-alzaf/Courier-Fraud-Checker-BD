<?php
namespace Alzaf\CourierFraudCheckerBd\Supports;

use Alzaf\CourierFraudCheckerBd\Services\CarryBeeService;
use Alzaf\CourierFraudCheckerBd\Services\PathaoService;
use Alzaf\CourierFraudCheckerBd\Services\RedxService;
use Alzaf\CourierFraudCheckerBd\Services\SteadfastService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourierFraudCheckerSupport
{
    public function __construct(protected Container $container)
    {}

    public function check(string $phoneNumber, bool $is_disable_cache = true): array
    {

        $data = [];
        if (config('courier-fraud-checker-bd.steadfast.enable')) {
            $data['steadfast'] = $this->safeServiceCall('steadfast', SteadfastService::class, $phoneNumber, $is_disable_cache);
        }
        if (config('courier-fraud-checker-bd.pathao.enable')) {
            $data['pathao'] = $this->safeServiceCall('pathao', PathaoService::class, $phoneNumber, $is_disable_cache);
        }
        if (config('courier-fraud-checker-bd.redx.enable')) {
            $data['redx'] = $this->safeServiceCall('redx', RedxService::class, $phoneNumber, $is_disable_cache);
        }
        if (config('courier-fraud-checker-bd.carrybee.enable')) {
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

            $result = Cache::remember($cacheKey, now()->addDays(5), $fetchStats);
            if (is_array($result)) {
                return $result;
            }

            Cache::forget($cacheKey);
            throw new \UnexpectedValueException("Invalid response format from {$service}");

        } catch (\Throwable $exception) {
            if ($shouldUseCache) {
                Cache::forget($cacheKey);
            }

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
}
