<?php
namespace Alzaf\BdCourier\Services\FraudCheck;

use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Supports\DeliveryStatsCalculator;
use Alzaf\BdCourier\Traits\ApiTokenManager;
use Illuminate\Support\Facades\Http;

class CarryBeeService
{
    use ApiTokenManager;

    protected string $phone;
    protected string $password;

    protected string $tokenCacheKey = 'courier_fraud_checker_bd:carrybee_token';

    protected const LOGIN_URL = 'https://api-merchant.carrybee.com/api/v2/login';

    protected string $successUrl;

    public function __construct()
    {
        // Validate config presence
        CourierFraudCheckerHelper::checkRequiredConfig([
            'bd-courier.carrybee.phone',
            'bd-courier.carrybee.password',
            'bd-courier.carrybee.business_id',
        ]);

        // Load from config
        $this->phone    = config('bd-courier.carrybee.phone');
        $this->password = config('bd-courier.carrybee.password');

        $businessId = config('bd-courier.carrybee.business_id');

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
    
    public function add()
    {
        
    }
}
