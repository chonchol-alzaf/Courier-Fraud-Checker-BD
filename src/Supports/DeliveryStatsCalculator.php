<?php
namespace Alzaf\BdCourier\Supports;

use App\Models\ParentOrder;

class DeliveryStatsCalculator
{
    public static function calculate(int $success, int $cancel): array
    {
        $total = $success + $cancel;

        $successRate = $total > 0
            ? round(($success / $total) * 100, 2)
            : null;

        $cancelRate = $total > 0
            ? round(($cancel / $total) * 100, 2)
            : null;

        return [
            'success'      => $success,
            'cancel'       => $cancel,
            'total'        => $total,
            'success_rate' => $successRate,
            'cancel_rate' => $cancelRate,
            'risk_level'   => self::calculateRiskLevel($successRate),
        ];
    }

    private static function calculateRiskLevel(?float $rate): string
    {
        if ($rate === null) {
            return ParentOrder::RISK_LEVEL['NEW_CUSTOMER'];
        }

        return match (true) {
            $rate >= 70 => ParentOrder::RISK_LEVEL['SAFE'],
            $rate >= 50 => ParentOrder::RISK_LEVEL['WARNING'],
            $rate >= 35 => ParentOrder::RISK_LEVEL['RISKY'],
            default     => ParentOrder::RISK_LEVEL['REJECT'],
        };
    }
}
