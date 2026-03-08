<?php
namespace Alzaf\CourierFraudCheckerBd\Supports;

use Alzaf\CourierFraudCheckerBd\Services\CarryBeeService;
use Alzaf\CourierFraudCheckerBd\Services\FreeFraudChecker;
use Alzaf\CourierFraudCheckerBd\Services\PathaoService;
use Alzaf\CourierFraudCheckerBd\Services\RedxService;
use Alzaf\CourierFraudCheckerBd\Services\SteadfastService;

class CourierFraudCheckerSupport
{
    public function __construct(
        protected SteadfastService $steadfastService,
        protected PathaoService $pathaoService,
        protected RedxService $redxService,
        protected FreeFraudChecker $freeFraudChecker,
        protected CarryBeeService $carryBeeService
    ) {
    }

    public function check($phoneNumber)
    {
        $data = [];
        if (config('courier-fraud-checker-bd.steadfast.enable')) {
            $data['steadfast'] = $this->steadfastService->getCustomerDeliveryStats($phoneNumber);
        }
        if (config('courier-fraud-checker-bd.pathao.enable')) {
            $data['pathao'] = $this->pathaoService->getCustomerDeliveryStats($phoneNumber);
        }
        if (config('courier-fraud-checker-bd.redx.enable')) {
            $data['redx'] = $this->redxService->getCustomerDeliveryStats($phoneNumber);
        }
        if (config('courier-fraud-checker-bd.carrybee.enable')) {
            $data['carrybee'] = $this->carryBeeService->getCustomerDeliveryStats($phoneNumber);
        }
        return $data;
    }
}
