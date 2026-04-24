<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de consulta pública PNCP (sem autenticação)
    |--------------------------------------------------------------------------
    |
    | Documentação: https://pncp.gov.br/api/consulta/swagger-ui/index.html
    | Itens da compra (valor estimado): GET …/orgaos/{cnpj}/compras/{ano}/{sequencial}/itens
    | Endpoint usado: GET /v1/contratacoes/publicacao
    | Datas no formato AAAAMMDD. Parâmetro obrigatório: codigoModalidadeContratacao.
    | Paginação nativa do PNCP: query `pagina` (>=1, obrigatório) e `tamanhoPagina` (opcional;
    | padrão no manual 50 registros, máx. 500 — o gestor usa 10–100 via backend).
    |
    | Exemplo cURL (mesmos nomes enviados por PncpConsultaService::contratacoesPublicacao):
    | curl -sG 'https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao' \\
    |   --data-urlencode 'dataInicial=20260422' \\
    |   --data-urlencode 'dataFinal=20260423' \\
    |   --data-urlencode 'codigoModalidadeContratacao=6' \\
    |   --data-urlencode 'pagina=1' \\
    |   --data-urlencode 'tamanhoPagina=10' \\
    |   --data-urlencode 'uf=AL' \\
    |   -H 'Accept: application/json'
    |
    | Opcional: codigoMunicipioIbge, cnpj, codigoUnidadeAdministrativa, codigoModoDisputa, idUsuario.
    |
    */

    'consulta_base_url' => env('PNCP_CONSULTA_BASE_URL', 'https://pncp.gov.br/api/consulta'),

    /*
    | Base da API PNCP usada para leitura de itens da compra (lista pública).
    | Documentação geral: manual de integração PNCP (serviços de compras/itens).
    */
    'integracao_base_url' => env('PNCP_INTEGRACAO_BASE_URL', 'https://pncp.gov.br/api/pncp'),

    /* Tempo máximo por chamada HTTP ao PNCP (o governo pode responder devagar). Ajuste com PNCP_TIMEOUT no .env. */
    'timeout_seconds' => (int) env('PNCP_TIMEOUT', 90),

    /* Timeout só de conexão TCP/TLS (evita pendurar o PHP-FPM se o PNCP não aceitar conexão). */
    'connect_timeout_seconds' => (int) env('PNCP_CONNECT_TIMEOUT', 25),

    /*
    | Retentativas para contratações/publicação (502/503/504/429/500 e falhas de rede).
    | Backoff: base_sleep_ms * tentativa + jitter leve no código.
    */
    'retry_attempts' => max(1, (int) env('PNCP_RETRY_ATTEMPTS', 5)),
    'retry_base_sleep_ms' => max(50, (int) env('PNCP_RETRY_BASE_SLEEP_MS', 600)),

    /*
    | Teto de duração da consulta "contratações/publicação" (várias retentativas somadas).
    | Evita ultrapassar o read_timeout do Nginx/PHP-FPM (~60–120s em muitos hosts).
    */
    'publicacao_max_total_seconds' => max(20, (int) env('PNCP_PUBLICACAO_MAX_TOTAL_SECONDS', 95)),

    /* Timeout HTTP por tentativa só neste endpoint (pode ser menor que timeout_seconds geral). */
    'publicacao_attempt_timeout_seconds' => max(10, (int) env('PNCP_PUBLICACAO_ATTEMPT_TIMEOUT', 42)),

    /*
    | Explorar órgãos via publicações (deduplicação por CNPJ do órgão comprador).
    */
    'explorar_dias' => (int) env('PNCP_EXPLORAR_DIAS', 90),

    /** Código de modalidade PNCP (ex.: 6 = pregão eletrônico). */
    'explorar_codigo_modalidade' => (int) env('PNCP_EXPLORAR_MODALIDADE', 6),

];
