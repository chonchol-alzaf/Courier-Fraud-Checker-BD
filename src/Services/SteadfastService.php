<?php
namespace Alzaf\BdCourier\Services;

use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Supports\DeliveryStatsCalculator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

class SteadfastService
{
    protected string $email;
    protected string $password;

    protected const LOGIN_PAGE = 'https://steadfast.com.bd/login';
    protected const LOGIN_URL  = 'https://steadfast.com.bd/login';

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker.steadfast.user',
            'courier-fraud-checker.steadfast.password',
        ]);

        $this->email    = config('courier-fraud-checker.steadfast.user');
        $this->password = config('courier-fraud-checker.steadfast.password');
    }

    public function getCustomerDeliveryStats(string $phoneNumber): array
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $cookieJar = new CookieJar();

        $client = Http::withOptions([
            'cookies' => $cookieJar,
        ]);

        // Step 1: login page
        $response = $client->get(self::LOGIN_PAGE);

        preg_match('/name="_token" value="(.*?)"/', $response->body(), $matches);
        $csrf = $matches[1] ?? null;

        if (! $csrf) {
            return ['error' => 'CSRF token not found'];
        }

        // Step 2: login
        $login = $client->asForm()->post(self::LOGIN_URL, [
            '_token'   => $csrf,
            'email'    => $this->email,
            'password' => $this->password,
        ]);

        if (! $login->successful() && ! $login->redirect()) {
            return ['error' => 'Login failed'];
        }

        // Step 3: main fraud endpoint
        $result = $client->get("https://steadfast.com.bd/user/frauds/check/{$phoneNumber}");

        if (! $result->successful()) {

            // fallback endpoint
            $result = $client->get("https://steadfast.com.bd/user/consignment/getbyphone/{$phoneNumber}");

            if (! $result->successful()) {
                return ['error' => 'Failed to fetch fraud data'];
            }
        }

        $data = $result->json();

        $success = (int) ($data['total_delivered'] ?? 0);
        $cancel  = (int) ($data['total_cancelled'] ?? 0);

        $stats = DeliveryStatsCalculator::calculate($success, $cancel);

        return array_merge([
            'data_type' => 'delivery',
        ], $stats);
    }
}
