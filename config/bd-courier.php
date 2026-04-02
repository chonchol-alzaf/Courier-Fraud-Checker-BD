<?php

use Alzaf\BdCourier\Enums\CourierEnum;
use App\Enums\OrderStatusEnum;
use App\Models\ParentOrder;

return [
    'default_parcel'              => env('DEFAULT_COURIER_PARCEL', 'pathao'),
    'risk_levels'                 => [
        'SAFE'         => ParentOrder::RISK_LEVEL['SAFE'],
        'WARNING'      => ParentOrder::RISK_LEVEL['WARNING'],
        'RISKY'        => ParentOrder::RISK_LEVEL['RISKY'],
        'REJECT'       => ParentOrder::RISK_LEVEL['REJECT'],
        'NEW_CUSTOMER' => ParentOrder::RISK_LEVEL['NEW_CUSTOMER'],
    ],

    CourierEnum::PATHAO->value    => [
        'enable'   => env('PATHAO_ENABLE', false),
        'outgoing' => [
            'username'      => env('PATHAO_USERNAME', 'test@pathao.com'),
            'password'      => env('PATHAO_PASSWORD', 'lovePathao'),
            'base_url'      => env('PATHAO_BASE_URL', 'https://courier-api-sandbox.pathao.com'),
            'client_id'     => env('PATHAO_CLIENT_ID', '7N1aMJQbWm'),
            'client_secret' => env('PATHAO_CLIENT_SECRET', 'wRcaibZkUdSNz2EI9ZyuXLlNrnAv0TdPUPXMnD39'),
        ],
        'incoming' => [
            'signature_header'    => 'X-PATHAO-Signature',
            'signature_value'     => env('PATHAO_WEBHOOK_SIGNATURE_VALUE', 'OATpB1Erwy2nBRNbAdEgSumu4Nafis31pQjEeCWLROARE'),
            'secret_header'       => 'X-Pathao-Merchant-Webhook-Integration-Secret',
            'secret_header_value' => env('PATHAO_WEBHOOK_INTEGRATION_SECRET', 'f3992ecc-59da-4cbe-a049-a13da2018d51'),
            'status_map'          => [
                'order.assigned-for-pickup'       => OrderStatusEnum::SHIPPING->value,
                'order.pickup-requested'          => OrderStatusEnum::SHIPPING->value,
                'order.pickup'                    => OrderStatusEnum::SHIPPING->value,
                'order.picked'                    => OrderStatusEnum::SHIPPING->value,
                'order.at-the-sorting-hub'        => OrderStatusEnum::SHIPPING->value,
                'order.received-at-last-mile-hub' => OrderStatusEnum::SHIPPING->value,
                'order.in-transit'                => OrderStatusEnum::SHIPPING->value,
                'order.assigned-for-delivery'     => OrderStatusEnum::SHIPPING->value,
                'order.delivered'                 => OrderStatusEnum::DELIVERED->value,
                'order.delivery-failed'           => OrderStatusEnum::DELIVERED_FAILED->value,
                'order.return'                    => OrderStatusEnum::RETURNED->value,
                'order.paid-return'               => OrderStatusEnum::RETURNED->value,
            ],
        ],
    ],

    CourierEnum::REDX->value      => [
        'enable'   => env('REDX_ENABLE', false),
        'outgoing' => [
            'phone'    => env('REDX_PHONE'),
            'password' => env('REDX_PASSWORD'),
            'base_url' => env('REDX_BASE_URL', 'sandbox.redx.com.bd/v1.0.0-beta'),
            'token'    => env('REDX_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwiaWF0IjoxNzM1NTMxNjU2LCJpc3MiOiJ0OTlnbEVnZTBUTm5MYTNvalh6MG9VaGxtNEVoamNFMyIsInNob3BfaWQiOjEsInVzZXJfaWQiOjZ9.zpKfyHK6zPBVaTrYevnCqnUA-e2jFKQJ7lK-z4aOx2g'),
        ],
        'incoming' => [
            'signature_query_param' => env('REDX_WEBHOOK_SIGNATURE_QUERY_PARAM', 'token'),
            'signature_value'       => env('REDX_WEBHOOK_SIGNATURE_VALUE', 'OATpB1Erwy2nBRNbAdEgSumu4Nafis31pQjEeCWLROARE'),
            'status_map'            => [
                'ready-for-delivery'   => OrderStatusEnum::SHIPPING->value,
                'delivery-in-progress' => OrderStatusEnum::SHIPPING->value,
                'agent-area-change'    => OrderStatusEnum::SHIPPING->value,
                'delivered'            => OrderStatusEnum::DELIVERED->value,
                'returned'             => OrderStatusEnum::RETURNED->value,
            ],
        ],

    ],

    CourierEnum::STEADFAST->value => [
        'enable'   => env('STEADFAST_ENABLE', false),

        'outgoing' => [
            'user'     => env('STEADFAST_USER'),
            'password' => env('STEADFAST_PASSWORD'),
        ],
        'incoming' => [
            'secret_header' => env('STEADFAST_WEBHOOK_SECRET_HEADER', 'X-Steadfast-Webhook-Secret'),
            'secret'        => env('STEADFAST_WEBHOOK_SECRET'),
        ],
    ],

    CourierEnum::CARRYBEE->value  => [
        'enable'   => env('CARRYBEE_ENABLE', false),
        'outgoing' => [
            'business_id' => env('CARRYBEE_BUSINESS_ID'),
            'phone'       => env('CARRYBEE_PHONE'),
            'password'    => env('CARRYBEE_PASSWORD'),
        ],

        'incoming' => [
            'secret_header' => env('CARRYBEE_WEBHOOK_SECRET_HEADER', 'X-Carrybee-Webhook-Secret'),
            'secret'        => env('CARRYBEE_WEBHOOK_SECRET'),
        ],
    ],
];
