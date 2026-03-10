<?php
namespace Alzaf\BdCourier\Services;

use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Supports\DeliveryStatsCalculator;
use Alzaf\BdCourier\Traits\ApiTokenManager;
use Illuminate\Support\Facades\Http;
use Shope\Core\Exceptions\CustomException;

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
            'bd-courier.redx.phone',
            'bd-courier.redx.password',
        ]);

        $this->phone    = config('bd-courier.redx.phone');
        $this->password = config('bd-courier.redx.password');

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

        if (! $response->successful()) {
            throw new CustomException("Failed to retrieve customer data", 500);
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
