<?php
namespace Alzaf\BdCourier\Services;

use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Traits\ApiTokenManager;
use Illuminate\Support\Facades\Http;

class PathaoService
{
    use ApiTokenManager;

    protected string $username;
    protected string $password;

    protected string $tokenCacheKey = 'courier_fraud_checker_bd:pathao_token';

    protected const LOGIN_URL   = 'https://merchant.pathao.com/api/v1/login';
    protected const SUCCESS_URL = 'https://merchant.pathao.com/api/v1/user/success';

    protected array $customerRating = [
        'excellent_customer' => [
            'rating'       => 'excellent_customer',
            'risk_level'   => 'low',
            'success_rate' => 95,
        ],
        'good_customer'      => [
            'rating'       => 'good_customer',
            'risk_level'   => 'low',
            'success_rate' => 85,
        ],
        'moderate_customer'  => [
            'rating'       => 'moderate_customer',
            'risk_level'   => 'medium',
            'success_rate' => 70,
        ],
        'risky_customer'     => [
            'rating'       => 'risky_customer',
            'risk_level'   => 'very high',
            'success_rate' => 30,
        ],
        'new_customer'       => [
            'rating'       => 'new_customer',
            'risk_level'   => 'unknown',
            'success_rate' => null,
        ],
    ];

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker.pathao.user',
            'courier-fraud-checker.pathao.password',
        ]);

        $this->username = config('courier-fraud-checker.pathao.user');
        $this->password = config('courier-fraud-checker.pathao.password');
    }

    protected function requestNewToken(): ?string
    {
        $response = Http::timeout(10)->post(self::LOGIN_URL, [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'access_token');
    }

    public function getCustomerDeliveryStats(string $phoneNumber)
    {
        $response = $this->requestWithToken(function ($token) use ($phoneNumber) {

            return Http::withToken($token)
                ->post(self::SUCCESS_URL, [
                    'phone'      => $phoneNumber,
                    'show_count' => true,
                ]);
        });

        $rating = data_get($response->json(), 'data.customer_rating');

        if (! $rating || ! isset($this->customerRating[$rating])) {
            return ['error' => 'Invalid customer rating received'];
        }

        return array_merge(
            ['data_type' => 'rating'],
            $this->customerRating[$rating]
        );
    }

}
