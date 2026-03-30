<?php
namespace Alzaf\BdCourier\Services\FraudCheck;

use Alzaf\BdCourier\Contracts\CourierServiceInterface;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Traits\ApiTokenManager;
use App\Models\ParentOrder;
use Illuminate\Support\Facades\Http;

class PathaoService implements CourierServiceInterface
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
            'risk_level'   => ParentOrder::RISK_LEVEL['SAFE'],
            'success_rate' => 95,
        ],
        'good_customer'      => [
            'rating'       => 'good_customer',
            'risk_level'   => ParentOrder::RISK_LEVEL['SAFE'],
            'success_rate' => 85,
        ],
        'moderate_customer'  => [
            'rating'       => 'moderate_customer',
            'risk_level'   => ParentOrder::RISK_LEVEL['WARNING'],
            'success_rate' => 70,
        ],
        'risky_customer'     => [
            'rating'       => 'risky_customer',
            'risk_level'   => ParentOrder::RISK_LEVEL['RISKY'],
            'success_rate' => 30,
        ],
        'new_customer'       => [
            'rating'       => 'new_customer',
            'risk_level'   => ParentOrder::RISK_LEVEL['NEW_CUSTOMER'],
            'success_rate' => null,
        ],
    ];

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
