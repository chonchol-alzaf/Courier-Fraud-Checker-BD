<?php

return [
    'default_parcel'=> env("DEFAULT_COURIER_PARCEL",'pathao'),
    'pathao'    => [
        'enable'   => env('PATHAO_ENABLE', false),
        'user'     => env('PATHAO_USER'),
        'password' => env('PATHAO_PASSWORD'),
    ],

    'redx'      => [
        'enable'   => env('REDX_ENABLE', false),
        'phone'    => env('REDX_PHONE'),
        'password' => env('REDX_PASSWORD'),
    ],

    'steadfast' => [
        'enable'   => env('STEADFAST_ENABLE', false),
        'user'     => env('STEADFAST_USER'),
        'password' => env('STEADFAST_PASSWORD'),
    ],

    'carrybee'  => [
        'enable'      => env('CARRYBEE_ENABLE', false),
        'business_id' => env('CARRYBEE_BUSINESS_ID'),
        'phone'       => env('CARRYBEE_PHONE'),
        'password'    => env('CARRYBEE_PASSWORD'),
    ],
];
