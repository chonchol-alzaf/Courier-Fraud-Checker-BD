<?php

namespace Alzaf\BdCourier\Contracts;

interface FraudCheckServiceInterface
{
    public function getCustomerDeliveryStats(string $phoneNumber): array;
}
