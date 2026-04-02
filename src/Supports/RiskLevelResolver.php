<?php

namespace Alzaf\BdCourier\Supports;

class RiskLevelResolver
{
    private const DEFAULT_LEVELS = [
        'SAFE' => 'safe',
        'WARNING' => 'warning',
        'RISKY' => 'risky',
        'REJECT' => 'reject',
        'NEW_CUSTOMER' => 'new_customer',
    ];

    public static function get(string $key): string
    {
        $configuredLevels = config('bd-courier.risk_levels', []);
        if (is_array($configuredLevels) && isset($configuredLevels[$key]) && is_string($configuredLevels[$key]) && $configuredLevels[$key] !== '') {
            return $configuredLevels[$key];
        }

        return self::DEFAULT_LEVELS[$key] ?? strtolower($key);
    }
}
