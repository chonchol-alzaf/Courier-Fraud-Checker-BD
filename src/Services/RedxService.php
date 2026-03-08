<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerHelper;
use Alzaf\CourierFraudCheckerBd\Supports\DeliveryStatsCalculator;
use Alzaf\CourierFraudCheckerBd\Traits\ApiTokenManager;
use Illuminate\Support\Facades\Http;

class RedxService
{
    use ApiTokenManager;

    protected string $tokenCacheKey  = 'courier_fraud_checker_bd:redx_token';
    protected int $tokenCacheMinutes = 50;

    protected string $phone;
    protected string $password;

    protected const LOGIN_URL = 'https://api.redx.com.bd/v4/auth/login';
    protected const STATS_URL = 'https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate';

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker-bd.redx.phone',
            'courier-fraud-checker-bd.redx.password',
        ]);

        $this->phone    = config('courier-fraud-checker-bd.redx.phone');
        $this->password = config('courier-fraud-checker-bd.redx.password');

        CourierFraudCheckerHelper::validatePhoneNumber($this->phone);
    }

    /**
     * Trait required method
     */
    protected function requestNewToken(): ?string
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept'     => 'application/json, text/plain, */*',
            ])
            ->post(self::LOGIN_URL, [
                'phone'    => '88' . $this->phone,
                'password' => $this->password,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'data.accessToken');
    }

    public function getCustomerDeliveryStats(string $queryPhone): array
    {
        CourierFraudCheckerHelper::validatePhoneNumber($queryPhone);

        try {

            $response = $this->requestWithToken(function ($token) use ($queryPhone) {

                return Http::timeout(10)
                    ->withHeaders([
                        'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                        'Accept'       => 'application/json, text/plain, */*',
                        'Content-Type' => 'application/json',
                    ])
                    ->withToken($token)
                    ->get(self::STATS_URL, [
                        'phoneNumber' => '88' . $queryPhone,
                    ]);

            });

        } catch (\Throwable $e) {
            return [
                'error'   => 1,
                'success' => 'Threshold hit, wait a minute',
                'cancel'  => 'Threshold hit, wait a minute',
                'total'   => 'Threshold hit, wait a minute',
            ];
        }

        if (! $response->successful()) {
            return [
                'error'  => 'Failed to retrieve customer data',
                'status' => $response->status(),
            ];
        }

        $object = $response->json('data') ?? [];

        $success = (int) ($object['deliveredParcels'] ?? 0);
        $total   = (int) ($object['totalParcels'] ?? 0);
        $cancel  = max($total - $success, 0);

        $stats = DeliveryStatsCalculator::calculate($success, $cancel);

        return array_merge([
            'data_type' => 'delivery',
        ], $stats);
    }
}
