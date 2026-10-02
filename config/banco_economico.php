<?php

return [
    'urls' => [
        'CERTIFICACION' => env('BANCO_ECONOMICO_URL_CERTIFICACION', 'https://apimktdesa.baneco.com.bo/ApiGateway'),
        'PRODUCCION' => env('BANCO_ECONOMICO_URL_PRODUCCION', 'https://apimkt.baneco.com.bo/ApiGateway/'),
    ],

    'fake_mode' => env('BANCO_ECONOMICO_FAKE_MODE', false),

    'timeout_default' => 20,
    'retry_default' => 3,
    'retry_delay_default' => 1000,
    'token_cache_ttl_default' => 3300,
    'moneda_default' => 'BOB',
    'branch_code_default' => 'S0001',
];