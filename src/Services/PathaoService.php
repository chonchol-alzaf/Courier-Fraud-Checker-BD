<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Helpers\CourierFraudCheckerHelper;
use Illuminate\Support\Facades\Http;

class PathaoService
{
    protected string $username;
    protected string $password;

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker-bd.pathao.user',
            'courier-fraud-checker-bd.pathao.password',
        ]);

        $this->username = config('courier-fraud-checker-bd.pathao.user');
        $this->password = config('courier-fraud-checker-bd.pathao.password');
    }

    public function pathao($phoneNumber)
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $response = Http::post('https://merchant.pathao.com/api/v1/login', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            return ['error' => 'Failed to authenticate with Pathao'];
        }

        $data        = $response->json();
        $accessToken = trim($data['access_token'] ?? '');

        if (! $accessToken) {
            return ['error' => 'No access token received from Pathao'];
        }

        $resultResponse = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post('https://merchant.pathao.com/api/v1/user/success', [
            'phone'      => $phoneNumber,
            'show_count' => true,
        ]);

        if (! $resultResponse->successful()) {
            return ['error' => 'Failed to retrieve customer data', 'status' => $resultResponse->status()];
        }

        $object = $resultResponse->json('data');

        return [
            'customer_rating' => $object['customer_rating'] ?? "new_customer",
        ];
    }
}
