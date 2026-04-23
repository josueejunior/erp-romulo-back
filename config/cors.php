<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | Origens permitidas (lista explícita).
    | CORS_ALLOWED_ORIGINS no .env é SOMADO à lista base — evita produção “substituir”
    | o gestor e o browser acusar CORS sem Access-Control-Allow-Origin.
    */
    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        'trim',
        array_merge(
            [
                'https://gestor.addsimp.com',
                'https://www.gestor.addsimp.com',
                'https://gestor.addsimp.com.br',
                'https://www.gestor.addsimp.com.br',
                'http://localhost:3000',
                'http://localhost:5173',
            ],
            env('CORS_ALLOWED_ORIGINS')
                ? explode(',', (string) env('CORS_ALLOWED_ORIGINS'))
                : [],
        ),
    )))),

    // Padrões regex (case-insensitive) — cobre subdomínios *.addsimp.com
    'allowed_origins_patterns' => [
        '#^https?://.*\.addsimp\.com$#i',
        '#^https?://.*\.addsimp\.com\.br$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Quando allowed_origins é '*', supports_credentials deve ser false
    // (restrição do CORS: não pode usar credentials com origem *)
    'supports_credentials' => false,

];







