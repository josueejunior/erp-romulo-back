<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercadopago' => [
        'access_token' => env('MP_ACCESS_TOKEN'),
        'public_key' => env('MP_PUBLIC_KEY'),
        'sandbox' => env('MP_SANDBOX', true),
    ],

    'discord_errors' => [
        // Ideal: mover para variável de ambiente DISCORD_500_WEBHOOK
        'webhook' => env('DISCORD_500_WEBHOOK', 'https://discord.com/api/webhooks/1470828021337555034/gM1pRIVST25JHZtKHGCfyh9H_RjH8BjbVUwhVaEcJDXVfDi7qVVv5FjKgBJkXt_3bSr_'),
    ],

    'discord_audit' => [
        // Ideal: mover para variável de ambiente DISCORD_AUDIT_WEBHOOK
        'webhook' => env('DISCORD_AUDIT_WEBHOOK', 'https://discord.com/api/webhooks/1470828754384191549/NtjGFVQOe3mxVFQj9twDLZj4he75JWNoKwNTCAE7O0aPi64j3DO_HDD2AQ-_wX0kqMkn'),
    ],

];
