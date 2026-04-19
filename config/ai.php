<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ollama (extração / análise de edital — uso opcional no backend)
    |--------------------------------------------------------------------------
    |
    | URL base do serviço Ollama (ex.: instância interna ou reverse proxy).
    | Não chame o Ollama a partir do browser em produção; use sempre o Laravel
    | ou um worker dedicado, com autenticação e limites de taxa.
    |
    | Na stack Docker (recomendado para o Laravel): http://ollama:11434
    | Acesso externo via Nginx: https://ollama.addsimp.com
    |
    */

    'ollama_base_url' => rtrim((string) env('OLLAMA_BASE_URL', 'http://ollama:11434'), '/'),

    'ollama_timeout_seconds' => (int) env('OLLAMA_TIMEOUT', 120),

];
