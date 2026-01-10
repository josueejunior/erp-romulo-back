<?php

// 🔥 PRODUÇÃO: Validar e corrigir configurações SMTP
// Prevenir uso de mailpit/localhost em produção
// IMPORTANTE: Usar operador ?? para garantir valores padrão mesmo com config cache
$mailHost = env('MAIL_HOST') ?? 'smtp.hostinger.com';
$mailPort = (int) (env('MAIL_PORT') ?? 587); // Hostinger geralmente usa 587 com TLS

$invalidHosts = ['mailpit', 'localhost', '127.0.0.1'];
if (in_array(strtolower($mailHost), array_map('strtolower', $invalidHosts)) || $mailPort === 1025) {
    // Corrigir automaticamente para configurações de produção
    // Nota: Não usar Log aqui - arquivos de config não suportam facades durante config:cache
    $mailHost = 'smtp.hostinger.com';
    $mailPort = 587;
}

// Determinar encryption baseado na porta
// Hostinger: 587 = TLS, 465 = SSL
$mailEncryption = env('MAIL_ENCRYPTION');
if (!$mailEncryption || $mailEncryption === 'null' || $mailEncryption === '') {
    $mailEncryption = ($mailPort === 587) ? 'tls' : (($mailPort === 465) ? 'ssl' : 'tls');
}

// 🔥 CREDENCIAIS: Garantir valores padrão mesmo se env() retornar null/false/empty (com config cache)
// Quando há config cache ativo, env() pode retornar null mesmo que exista no .env
$mailUsername = env('MAIL_USERNAME');
if (empty($mailUsername) || $mailUsername === false || $mailUsername === null || $mailUsername === 'null') {
    $mailUsername = 'naoresponda@addsimp.com';
}

$mailPassword = env('MAIL_PASSWORD');
if (empty($mailPassword) || $mailPassword === false || $mailPassword === null || $mailPassword === 'null') {
    $mailPassword = 'C/k6@!S0';
}

$mailFromAddress = env('MAIL_FROM_ADDRESS');
if (!$mailFromAddress || $mailFromAddress === 'null' || $mailFromAddress === '') {
    $mailFromAddress = 'naoresponda@addsimp.com';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            // 🔥 PRODUÇÃO: Valores validados para garantir SMTP de produção
            // Usar variáveis já validadas para garantir credenciais mesmo com config cache
            'host' => $mailHost,
            'port' => $mailPort,
            // Hostinger: porta 587 = TLS (recomendado), porta 465 = SSL
            'encryption' => $mailEncryption,
            'username' => $mailUsername,
            'password' => $mailPassword,
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN') ?? parse_url((string) (env('APP_URL') ?? 'http://localhost'), PHP_URL_HOST),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => $mailFromAddress,
        'name' => env('MAIL_FROM_NAME') ?? 'Sistema ERP - Gestão de Licitações',
    ],

];
