<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Helpers\CourierFraudCheckerHelper;
use Illuminate\Support\Facades\Http;

class FreeFraudChecker
{
    public function freeFraud($phoneNumber)
    {
        CourierFraudCheckerHelper::validatePhoneNumber($phoneNumber);

        $response = Http::withHeaders([
            "Content-Type"     => "application/json",
            "accept"           => "application/json",
            'Referer'          => 'https://fraudchecker.link/free-fraud-checker-bd/',
            'Origin'           => 'https://fraudchecker.link',
            'User-Agent'       => 'Mozilla/5.0',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->get('https://fraudchecker.link/free-fraud-checker-bd/api/search.php', [
                'phone' => $phoneNumber,
            ]);

        if (! $response->successful()) {
            return ['error' => 'Failed to retrieve customer data', 'status' => $response->status()];
        }

        $object = $response->json('data');

        $couriers = $object['couriers'] ?? [];

        return [
            'total'        => $object['totalOrders'] ?? 0,
            'success'      => $object['totalDelivered'] ?? 0,
            'cancel'       => $object['totalCancelled'] ?? 0,
            'deliveryRate' => $object['deliveryRate'] ?? 0,
            "couriers"     => $couriers,
        ];

    }
}
