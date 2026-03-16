<?php
namespace Alzaf\BdCourier\Services\Parcel;

use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Supports\DeliveryStatsCalculator;
use Alzaf\BdCourier\Traits\ApiTokenManager;
use Alzaf\BdCourier\Traits\ParcelValidationTrait;
use Illuminate\Support\Facades\Http;

class CarryBeeService
{
   use ParcelValidationTrait;
}
