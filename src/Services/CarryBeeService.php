<?php
namespace Alzaf\CourierFraudChecker\Services;

use Alzaf\CourierFraudChecker\Supports\CourierFraudCheckerHelper;
use Alzaf\CourierFraudChecker\Supports\DeliveryStatsCalculator;
use Alzaf\CourierFraudChecker\Traits\ApiTokenManager;
use Illuminate\Support\Facades\Http;

class CarryBeeService
{
    use ApiTokenManager;

    protected string $phone;
    protected string $password;

    protected string $tokenCacheKey = 'courier_fraud_checker_bd:carrybee_token';

    protected const LOGIN_URL   = 'https://api-merchant.carrybee.com/api/v2/login';

    protected string $successUrl;


    public function __construct()
    {
        // Validate config presence
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker-bd.carrybee.phone',
            'courier-fraud-checker-bd.carrybee.password',
            'courier-fraud-checker-bd.carrybee.business_id',
        ]);

        // Load from config
        $this->phone    = config('courier-fraud-checker-bd.carrybee.phone');
        $this->password = config('courier-fraud-checker-bd.carrybee.password');

        $businessId = config('courier-fraud-checker-bd.carrybee.business_id');

        $this->successUrl = "https://api-merchant.carrybee.com/api/v2/businesses/{$businessId}/fraud-check/";

        CourierFraudCheckerHelper::validatePhoneNumber($this->phone);
    }

    protected function requestNewToken(): ?string
    {
        $response = Http::timeout(10)->post(self::LOGIN_URL, [
            'phone'    => '+88' . $this->phone,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'data.accessToken');
    }

    public function getCustomerDeliveryStats(string $phoneNumber)
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $response = $this->requestWithToken(function ($token) use ($phoneNumber) {

            return Http::withToken($token)
                ->get($this->successUrl . $phoneNumber);
        });

        if (! $response->successful()) {
            return [
                'error'  => 'Failed to retrieve customer data',
                'status' => $response->status(),
            ];
        }

        $data = data_get($response->json(), 'data');

        $total  = (int) ($data['total_order'] ?? 0);
        $cancel = (int) ($data['cancelled_order'] ?? 0);
        $cancel = max(0, min($cancel, $total));

        $success = max($total - $cancel, 0);

        $stats = DeliveryStatsCalculator::calculate($success, $cancel);

        return array_merge([
            'data_type' => 'delivery',
        ], $stats);
    }
}
