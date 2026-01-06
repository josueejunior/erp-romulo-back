<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Permitir todas as origens (API pública)
    // Se quiser restringir, defina CORS_ALLOWED_ORIGINS no .env
    // IMPORTANTE: Se CORS_ALLOWED_ORIGINS não estiver definido ou estiver vazio, permite todas as origens (*)
    'allowed_origins' => (function() {
        $envValue = env('CORS_ALLOWED_ORIGINS');
        // Se não estiver definido, vazio, ou for explicitamente '*', permitir todas as origens
        if (empty($envValue) || $envValue === '*') {
            return ['*'];
        }
        // Caso contrário, usar a lista definida
        return array_filter(
            array_map('trim', 
                explode(',', $envValue)
            )
        );
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Quando allowed_origins é '*', supports_credentials deve ser false
    // (restrição do CORS: não pode usar credentials com origem *)
    'supports_credentials' => (function() {
        $envValue = env('CORS_ALLOWED_ORIGINS');
        // Se não estiver definido, vazio, ou for '*', não usar credentials
        if (empty($envValue) || $envValue === '*') {
            return false;
        }
        return true;
    })(),

];







