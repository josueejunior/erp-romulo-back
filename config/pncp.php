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

    /*
    | Explorar órgãos via publicações (deduplicação por CNPJ do órgão comprador).
    */
    'explorar_dias' => (int) env('PNCP_EXPLORAR_DIAS', 90),

    /** Código de modalidade PNCP (ex.: 6 = pregão eletrônico). */
    'explorar_codigo_modalidade' => (int) env('PNCP_EXPLORAR_MODALIDADE', 6),

];
