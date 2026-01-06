<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    |
    | Chave secreta para assinar e validar tokens JWT.
    | Use uma chave forte e única. Recomendado: usar APP_KEY como fallback.
    |
    */
    'secret' => env('JWT_SECRET', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | JWT Issuer
    |--------------------------------------------------------------------------
    |
    | Identificador do emissor do token (normalmente a URL da API).
    |
    */
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | JWT Expiration Time
    |--------------------------------------------------------------------------
    |
    | Tempo de expiração do token em segundos.
    | Padrão: 3600 (1 hora)
    |
    */
    'expiration' => env('JWT_EXPIRATION', 3600),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | Algoritmo usado para assinar o token.
    | Padrão: HS256 (HMAC SHA-256)
    |
    */
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
];

