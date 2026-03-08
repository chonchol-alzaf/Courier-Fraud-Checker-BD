<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CarryBeeService
{
    protected string $cacheKey  = 'carrybee_access_token';
    protected int $cacheMinutes = 50;
    protected string $phone;
    protected string $password;

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

    private function getAccessToken()
    {
        $accessToken = Cache::get($this->cacheKey);
        if ($accessToken) {
            return $accessToken;
        }

        $login_response = Http::post("https://api-merchant.carrybee.com/api/v2/login", [
            'phone'    => '+88' . $this->phone,
            'password' => $this->password,
        ]);

        $accessToken = data_get($login_response->json(), 'data.accessToken');
        if ($accessToken) {
            Cache::put($this->cacheKey, $accessToken, now()->addMinutes($this->cacheMinutes));
        }

        return $accessToken;
    }

    public function getCustomerDeliveryStats(string $queryPhone)
    {
        CourierFraudCheckerHelper::validatePhoneNumber($queryPhone);

        $response = retry(2, function () use ($queryPhone) {

            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->get("https://api-merchant.carrybee.com/api/v2/businesses/15069/fraud-check/{$queryPhone}");

            if ($response->status() === 401) {
                Cache::forget($this->cacheKey);
                throw new \Exception("Token expired");
            }

            return $response;

        });

        $data = data_get($response->json(), 'data');

        $total   = $data['total_order'] ?? 0;
        $cancel  = $data['cancelled_order'] ?? 0;
        $success = $total + $cancel;
        $result  = [
            'total'   => $data['total_order'] ?? 0,
            'cancel'  => $data['cancelled_order'] ?? 0,
            'success' => $success,
        ];

        return $result;

    }
}
