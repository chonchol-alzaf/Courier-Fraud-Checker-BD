<?php

namespace Alzaf\BdCourier\Services\FraudCheck;

use Alzaf\BdCourier\Contracts\FraudCheckServiceInterface;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Supports\RiskLevelResolver;
use Alzaf\BdCourier\Traits\FraudCheckApiTokenManager;
use Illuminate\Support\Facades\Http;

class PathaoService implements FraudCheckServiceInterface
{
    use FraudCheckApiTokenManager;

    protected string $username;

    protected string $password;

    protected string $tokenCacheKey = 'courier_fraud_checker_bd:pathao_token';

    protected const LOGIN_URL = 'https://merchant.pathao.com/api/v1/login';

    protected const SUCCESS_URL = 'https://merchant.pathao.com/api/v1/user/success';

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'bd-courier.pathao.outgoing.username',
            'bd-courier.pathao.outgoing.password',
        ]);

        $this->username = config('bd-courier.pathao.outgoing.username');
        $this->password = config('bd-courier.pathao.outgoing.password');
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

    public function getCustomerDeliveryStats(string $phoneNumber): array
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $response = $this->requestWithToken(function ($token) use ($phoneNumber) {

            return Http::withToken($token)
                ->post(self::SUCCESS_URL, [
                    'phone' => $phoneNumber,
                    'show_count' => true,
                ]);
        });

        $customerRatings = $this->customerRatings();
        $rating = data_get($response->json(), 'data.customer_rating');

        if (! $rating || ! isset($customerRatings[$rating])) {
            return ['error' => 'Invalid customer rating received'];
        }

        return array_merge(
            ['data_type' => 'rating'],
            $customerRatings[$rating]
        );
    }

    protected function customerRatings(): array
    {
        return [
            'excellent_customer' => [
                'rating' => 'excellent_customer',
                'risk_level' => RiskLevelResolver::get('SAFE'),
                'success_rate' => 95,
            ],
            'good_customer' => [
                'rating' => 'good_customer',
                'risk_level' => RiskLevelResolver::get('SAFE'),
                'success_rate' => 85,
            ],
            'moderate_customer' => [
                'rating' => 'moderate_customer',
                'risk_level' => RiskLevelResolver::get('WARNING'),
                'success_rate' => 70,
            ],
            'risky_customer' => [
                'rating' => 'risky_customer',
                'risk_level' => RiskLevelResolver::get('RISKY'),
                'success_rate' => 30,
            ],
            'new_customer' => [
                'rating' => 'new_customer',
                'risk_level' => RiskLevelResolver::get('NEW_CUSTOMER'),
                'success_rate' => null,
            ],
        ];
    }
}
