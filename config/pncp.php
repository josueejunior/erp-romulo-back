<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de consulta pública PNCP (sem autenticação)
    |--------------------------------------------------------------------------
    |
    | Documentação: https://pncp.gov.br/api/consulta/swagger-ui/index.html
    | Endpoint usado: GET /v1/contratacoes/publicacao
    | Datas no formato AAAAMMDD. Parâmetro obrigatório: codigoModalidadeContratacao.
    | tamanhoPagina mínimo 10 (regra da API).
    |
    */

    'consulta_base_url' => env('PNCP_CONSULTA_BASE_URL', 'https://pncp.gov.br/api/consulta'),

    /*
    | Base da API PNCP usada para leitura de itens da compra (lista pública).
    | Documentação geral: manual de integração PNCP (serviços de compras/itens).
    */
    'integracao_base_url' => env('PNCP_INTEGRACAO_BASE_URL', 'https://pncp.gov.br/api/pncp'),

    'timeout_seconds' => (int) env('PNCP_TIMEOUT', 45),

];
