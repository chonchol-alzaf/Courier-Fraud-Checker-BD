<?php

use Alzaf\BdCourier\Enums\CourierEnum;
use App\Enums\OrderStatusEnum;

return [
    'default_parcel'              => env('DEFAULT_COURIER_PARCEL', 'pathao'),
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
                'assigned_for_pickup'       => OrderStatusEnum::SHIPPING->value,
                'pickup_requested'          => OrderStatusEnum::SHIPPING->value,
                'pickup'                    => OrderStatusEnum::SHIPPING->value,
                'order_picked'              => OrderStatusEnum::SHIPPING->value,
                'at_the_sorting_hub'        => OrderStatusEnum::SHIPPING->value,
                'received_at_last_mile_hub' => OrderStatusEnum::SHIPPING->value,
                'in_transit'                => OrderStatusEnum::SHIPPING->value,
                'assigned_for_delivery'     => OrderStatusEnum::SHIPPING->value,
                'delivered'                 => OrderStatusEnum::DELIVERED->value,
                'order_delivered'           => OrderStatusEnum::DELIVERED->value,
                'order_delivery_failed'     => OrderStatusEnum::DELIVERED_FAILED->value,
                'return'                    => OrderStatusEnum::RETURNED->value,
                'paid_return'               => OrderStatusEnum::RETURNED->value,
            ],
        ],
    ],

    CourierEnum::REDX->value      => [
        'enable'   => env('REDX_ENABLE', false),
        'outgoing' => [
            'phone'    => env('REDX_PHONE'),
            'password' => env('REDX_PASSWORD'),
        ],
        'incoming' => [
            'secret_header' => env('REDX_WEBHOOK_SECRET_HEADER', 'X-RedX-Webhook-Secret'),
            'secret'        => env('REDX_WEBHOOK_SECRET'),
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
