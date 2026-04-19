<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Google Cloud OAuth (Calendar + Docs)
    |--------------------------------------------------------------------------
    |
    | Crie credenciais "OAuth client ID" (tipo Web) no Google Cloud Console,
    | habilite as APIs "Google Calendar API" e "Google Docs API" no projeto,
    | e cadastre a URI de redirecionamento exatamente igual a redirect_uri abaixo.
    |
    */
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/api/v1/integrations/google/callback'),

    /*
    | Após o OAuth, o usuário é redirecionado para esta URL (frontend) com
    | ?google=connected ou ?google=error&mensagem=...
    */
    'frontend_redirect_url' => env('GOOGLE_FRONTEND_REDIRECT_URL', env('FRONTEND_URL', 'http://localhost:5173').'/configuracoes'),
];
