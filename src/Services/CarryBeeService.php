<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerHelper;
use Alzaf\CourierFraudCheckerBd\Supports\DeliveryStatsCalculator;
use Alzaf\CourierFraudCheckerBd\Traits\ApiTokenManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarryBeeService
{
    use ApiTokenManager;

    protected string $phone;
    protected string $password;

    protected string $tokenCacheKey = 'courier_fraud_checker_bd:carrybee_token';

    protected const LOGIN_URL   = 'https://api-merchant.carrybee.com/api/v2/login';
    protected const SUCCESS_URL = 'https://api-merchant.carrybee.com/api/v2/businesses/15069/fraud-check/';

    // TODO: business id dynamic fetch korte hobe

    public function __construct()
    {
        // Validate config presence
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker-bd.carrybee.phone',
            'courier-fraud-checker-bd.carrybee.password',
        ]);

        // Load from config
        $this->phone    = config('courier-fraud-checker-bd.carrybee.phone');
        $this->password = config('courier-fraud-checker-bd.carrybee.password');

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
        $response = $this->requestWithToken(function ($token) use ($phoneNumber) {

            return Http::withToken($token)
                ->get(self::SUCCESS_URL . $phoneNumber);
        });

        $data = data_get($response->json(), 'data');

        $total   = $data['total_order'] ?? 0;
        $cancel  = $data['cancelled_order'] ?? 0;
        $success = $total + $cancel;

        $stats = DeliveryStatsCalculator::calculate($success,$cancel);

        return array_merge([
            'data_type' => 'delivery',
        ], $stats);
    }
}
