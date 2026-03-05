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
        return [
            'steadfast' => $this->steadfastService->steadfast($phoneNumber),
            'pathao'    => $this->pathaoService->pathao($phoneNumber),
            'redx'      => $this->redxService->getCustomerDeliveryStats($phoneNumber),
            'freeFraud' => $this->freeFraudChecker->freeFraud($phoneNumber),
            // 'carrybee'  => $this->carryBeeService->getCustomerDeliveryStats($phoneNumber),
        ];
    }
}
