<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerHelper;
use Illuminate\Support\Facades\Http;

class PathaoService
{
    protected string $username;
    protected string $password;

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
            'risk_level'   => 'high',
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
            'courier-fraud-checker-bd.pathao.user',
            'courier-fraud-checker-bd.pathao.password',
        ]);

        $this->username = config('courier-fraud-checker-bd.pathao.user');
        $this->password = config('courier-fraud-checker-bd.pathao.password');
    }

    public function getCustomerDeliveryStats(string $phoneNumber): array
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $token = $this->getAccessToken();

        if (! $token) {
            return ['error' => 'Failed to authenticate with Pathao'];
        }

        $response = Http::timeout(10)
            ->withToken($token)
            ->post(self::SUCCESS_URL, [
                'phone'      => $phoneNumber,
                'show_count' => true,
            ]);

        if (! $response->successful()) {
            return [
                'error'  => 'Failed to retrieve customer data',
                'status' => $response->status(),
            ];
        }

        $rating = data_get($response->json(), 'data.customer_rating');

        if (! $rating || ! isset($this->customerRating[$rating])) {
            return ['error' => 'Invalid customer rating received'];
        }

        return array_merge(
            ['data_type' => 'rating'],
            $this->customerRating[$rating]
        );
    }

    private function getAccessToken(): ?string
    {
        $response = Http::timeout(10)->post(self::LOGIN_URL, [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return trim(data_get($response->json(), 'access_token'));
    }
}
