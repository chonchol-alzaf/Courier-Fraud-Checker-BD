<?php

namespace Alzaf\BdCourier\Supports;

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
            'success' => $success,
            'cancel' => $cancel,
            'total' => $total,
            'success_rate' => $successRate,
            'cancel_rate' => $cancelRate,
            'risk_level' => self::calculateRiskLevel($successRate),
        ];
    }

    private static function calculateRiskLevel(?float $rate): string
    {
        if ($rate === null) {
            return RiskLevelResolver::get('NEW_CUSTOMER');
        }

        return match (true) {
            $rate >= 70 => RiskLevelResolver::get('SAFE'),
            $rate >= 50 => RiskLevelResolver::get('WARNING'),
            $rate >= 35 => RiskLevelResolver::get('RISKY'),
            default => RiskLevelResolver::get('REJECT'),
        };
    }
}
