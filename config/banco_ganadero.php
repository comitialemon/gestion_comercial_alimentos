<?php

return [
    'urls' => [
        'CERTIFICACION' => env('BANCO_GANADERO_URL_CERTIFICACION', ''),
        'PRODUCCION' => env('BANCO_GANADERO_URL_PRODUCCION', ''),
    ],
    'fake_mode' => env('BANCO_GANADERO_FAKE_MODE', false),
    'timeout_default' => 20,
    'retry_default' => 3,
    'retry_delay_default' => 1000,
    'token_cache_ttl_default' => 1500,
    'moneda_default' => 'BOB',
];