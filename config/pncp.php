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

    /*
    | Explorar órgãos via publicações (deduplicação por CNPJ do órgão comprador).
    */
    'explorar_dias' => (int) env('PNCP_EXPLORAR_DIAS', 90),

    /** Código de modalidade PNCP (ex.: 6 = pregão eletrônico). */
    'explorar_codigo_modalidade' => (int) env('PNCP_EXPLORAR_MODALIDADE', 6),

];
