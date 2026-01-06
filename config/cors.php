<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Permitir todas as origens (API pública)
    // Se quiser restringir, defina CORS_ALLOWED_ORIGINS no .env
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') 
        ? array_filter(
            array_map('trim', 
                explode(',', env('CORS_ALLOWED_ORIGINS'))
            )
        )
        : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Quando allowed_origins é '*', supports_credentials deve ser false
    // (restrição do CORS: não pode usar credentials com origem *)
    'supports_credentials' => env('CORS_ALLOWED_ORIGINS') ? true : false,

];







