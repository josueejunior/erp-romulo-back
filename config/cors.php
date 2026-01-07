<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Origens permitidas
    // Se CORS_ALLOWED_ORIGINS estiver definido no .env, usa ele
    // Caso contrário, usa a lista abaixo
    'allowed_origins' => array_filter(
        array_map(
            'trim',
            env('CORS_ALLOWED_ORIGINS') 
                ? explode(',', env('CORS_ALLOWED_ORIGINS'))
                : [
                    'https://gestor.addsimp.com',
                    'https://www.gestor.addsimp.com',
                    'https://gestor.addsimp.com.br',
                    'https://www.gestor.addsimp.com.br',
                ]
        )
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Quando allowed_origins é '*', supports_credentials deve ser false
    // (restrição do CORS: não pode usar credentials com origem *)
    'supports_credentials' => false,

];







