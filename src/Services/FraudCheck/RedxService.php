<?php

namespace Alzaf\BdCourier\Services\FraudCheck;

use Alzaf\BdCourier\Contracts\FraudCheckServiceInterface;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Supports\DeliveryStatsCalculator;
use Alzaf\BdCourier\Traits\FraudCheckApiTokenManager;
use Illuminate\Support\Facades\Http;
use Shope\Core\Exceptions\CustomException;

class RedxService implements FraudCheckServiceInterface
{
    use FraudCheckApiTokenManager;

    protected string $tokenCacheKey = 'courier_fraud_checker_bd:redx_token';

    protected int $tokenCacheMinutes = 50;

    protected string $phone;

    protected string $password;

    protected const LOGIN_URL = 'https://api.redx.com.bd/v4/auth/login';

    protected const STATS_URL = 'https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate';

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'bd-courier.redx.outgoing.phone',
            'bd-courier.redx.outgoing.password',
        ]);

        $this->phone = config('bd-courier.redx.outgoing.phone');
        $this->password = config('bd-courier.redx.outgoing.password');

        CourierFraudCheckerHelper::validatePhoneNumber($this->phone);
    }

    /**
     * Trait required method
     */
    protected function requestNewToken(): ?string
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept' => 'application/json, text/plain, */*',
            ])
            ->post(self::LOGIN_URL, [
                'phone' => '88'.$this->phone,
                'password' => $this->password,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'data.accessToken');
    }

    public function getCustomerDeliveryStats(string $queryPhone): array
    {
        CourierFraudCheckerHelper::validatePhoneNumber($queryPhone);

        $response = $this->requestWithToken(function ($token) use ($queryPhone) {

            return Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'Accept' => 'application/json, text/plain, */*',
                    'Content-Type' => 'application/json',
                ])
                ->withToken($token)
                ->get(self::STATS_URL, [
                    'phoneNumber' => '88'.$queryPhone,
                ]);

        });

        if (! $response->successful()) {
            throw new CustomException('Failed to retrieve customer data', 500);
        }

        $object = $response->json('data') ?? [];

        return array_merge(
            ['data_type' => 'delivery'],
            self::statsFromPayload(is_array($object) ? $object : [])
        );
    }

    /**
     * Redx reports every parcel ever booked for the phone, including the ones still on the way. Only delivered
     * and returned parcels say anything about the customer, so parcels in transit are reported but not counted:
     * `total` and `success_rate` cover resolved parcels only, `booked` is Redx's full count.
     *
     * Returned parcels come from an explicit count when Redx sends one, otherwise from `returnPercentage` of the
     * booked parcels. Without either figure every undelivered parcel counts as returned, as it did before.
     *
     * @param  array  $data  the `data` object of the customer-success-return-rate response
     */
    public static function statsFromPayload(array $data): array
    {
        $booked = max(0, (int) ($data['totalParcels'] ?? 0));
        $delivered = min($booked, max(0, (int) ($data['deliveredParcels'] ?? 0)));
        $undelivered = $booked - $delivered;

        $returned = null;
        foreach (['returnedParcels', 'returnParcels', 'cancelledParcels'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $returned = (int) $data[$key];
                break;
            }
        }

        $returnPercentage = $data['returnPercentage'] ?? null;
        if ($returned === null && is_numeric($returnPercentage) && (float) $returnPercentage >= 0 && (float) $returnPercentage <= 100) {
            $returned = (int) round($booked * (float) $returnPercentage / 100);
        }

        $returnSource = $returned === null ? 'assumed' : 'reported';
        $returned = $returned === null ? $undelivered : min($undelivered, max(0, $returned));

        $segment = $data['customerSegment'] ?? null;

        return array_merge(DeliveryStatsCalculator::calculate($delivered, $returned), [
            'booked' => $booked,
            'in_transit' => $undelivered - $returned,
            'return_source' => $returnSource,
            'customer_segment' => is_string($segment) && $segment !== '' ? $segment : null,
        ]);
    }
}
