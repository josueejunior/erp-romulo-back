<?php

declare(strict_types=1);

namespace App\Services\Pncp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente mínimo para a API pública de consultas do PNCP.
 *
 * @see https://pncp.gov.br/api/consulta/swagger-ui/index.html
 */
final class PncpConsultaService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $integracaoBaseUrl,
        private readonly int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        $base = rtrim((string) config('pncp.consulta_base_url', 'https://pncp.gov.br/api/consulta'), '/');
        $integracao = rtrim((string) config('pncp.integracao_base_url', 'https://pncp.gov.br/api/pncp'), '/');

        return new self($base, $integracao, (int) config('pncp.timeout_seconds', 45));
    }

    /**
     * GET /v1/contratacoes/publicacao
     *
     * @param  array{
     *   data_inicial:string,
     *   data_final:string,
     *   codigo_modalidade:int,
     *   pagina?:int,
     *   tamanho_pagina?:int,
     *   uf?:string,
     *   codigo_ibge?:string,
     *   cnpj?:string
     * }  $params
     * @return array{data:array<int,mixed>,totalRegistros?:int,totalPaginas?:int,numeroPagina?:int,paginasRestantes?:int,empty?:bool}
     */
    public function contratacoesPublicacao(array $params): array
    {
        $query = [
            'dataInicial' => $this->toPncpDate($params['data_inicial']),
            'dataFinal' => $this->toPncpDate($params['data_final']),
            'codigoModalidadeContratacao' => (int) $params['codigo_modalidade'],
            'pagina' => max(1, (int) ($params['pagina'] ?? 1)),
            'tamanhoPagina' => max(10, min(100, (int) ($params['tamanho_pagina'] ?? 10))),
        ];

        if (!empty($params['uf'])) {
            $query['uf'] = strtoupper(substr((string) $params['uf'], 0, 2));
        }
        if (!empty($params['codigo_ibge'])) {
            $query['codigoIbge'] = preg_replace('/\D/', '', (string) $params['codigo_ibge']);
        }
        if (!empty($params['cnpj'])) {
            $query['cnpj'] = preg_replace('/\D/', '', (string) $params['cnpj']);
        }

        $url = $this->baseUrl.'/v1/contratacoes/publicacao';

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($url, $query);

        if (!$response->successful()) {
            Log::warning('PNCP consulta falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);
            throw new RuntimeException(
                $response->json('message')
                ?? 'Falha ao consultar o PNCP. Tente outro intervalo ou modalidade.'
            );
        }

        return $response->json() ?? ['data' => []];
    }

    /**
     * GET /v1/orgaos/{cnpj}/compras/{ano}/{sequencial} — dados da contratação (API consulta).
     *
     * @return array<string,mixed>
     */
    public function recuperarCompra(string $cnpj, int $ano, int $sequencial): array
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($cnpjLimpo) !== 14) {
            throw new RuntimeException('CNPJ do órgão inválido.');
        }

        $url = $this->baseUrl.'/v1/orgaos/'.$cnpjLimpo.'/compras/'.$ano.'/'.$sequencial;

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($url);

        if ($response->status() === 204) {
            return [];
        }

        if (!$response->successful()) {
            Log::warning('PNCP recuperar compra falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                $response->json('message')
                ?? 'Compra não encontrada no PNCP para o identificador informado.'
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * GET /v1/orgaos/{cnpj}/compras/{ano}/{sequencial}/itens — itens da compra (API pública PNCP).
     *
     * Tenta primeiro a base de integração ({@see $integracaoBaseUrl}) e, em 404,
     * a API de consulta — o PNCP pode expor o recurso em bases distintas conforme o ambiente.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listarItensCompra(string $cnpj, int $ano, int $sequencial): array
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($cnpjLimpo) !== 14) {
            throw new RuntimeException('CNPJ do órgão inválido.');
        }

        $paths = [
            $this->integracaoBaseUrl.'/v1/orgaos/'.$cnpjLimpo.'/compras/'.$ano.'/'.$sequencial.'/itens',
            $this->baseUrl.'/v1/orgaos/'.$cnpjLimpo.'/compras/'.$ano.'/'.$sequencial.'/itens',
        ];

        $lastStatus = null;
        foreach ($paths as $url) {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($url);

            $lastStatus = $response->status();

            if ($response->successful()) {
                $json = $response->json();

                return $this->normalizeItensCompraPayload($json);
            }

            if ($response->status() !== 404) {
                Log::warning('PNCP listar itens falhou', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                ]);
                throw new RuntimeException(
                    $response->json('message')
                    ?? 'Não foi possível carregar os itens desta compra no PNCP.'
                );
            }
        }

        Log::warning('PNCP listar itens: 404 em todas as bases tentadas', [
            'cnpj' => $cnpjLimpo,
            'ano' => $ano,
            'sequencial' => $sequencial,
            'last_status' => $lastStatus,
        ]);
        throw new RuntimeException('Lista de itens não encontrada no PNCP para esta compra.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItensCompraPayload(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        if (isset($json['data']) && is_array($json['data'])) {
            $rows = array_values(array_filter($json['data'], 'is_array'));

            return $rows;
        }

        if ($json !== [] && array_is_list($json)) {
            return array_values(array_filter($json, 'is_array'));
        }

        return [];
    }

    /**
     * Lista documentos/arquivos publicados da compra (editais, anexos, etc.).
     *
     * O PNCP expõe metadados e URL de download em endpoints do tipo:
     * {@code GET /v1/orgaos/{cnpj}/compras/{ano}/{sequencial}/arquivos}
     * (cada item costuma trazer {@code url}, {@code titulo}, {@code tipoDocumentoNome}, …).
     *
     * A base exata pode variar entre ambientes; tentamos primeiro a API de integração
     * (mesma família de URL usada em {@see listarItensCompra}) e, em 404, a API de consulta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarArquivosCompra(string $cnpj, int $ano, int $sequencial): array
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($cnpjLimpo) !== 14) {
            throw new RuntimeException('CNPJ do órgão inválido.');
        }

        $paths = [
            $this->integracaoBaseUrl.'/v1/orgaos/'.$cnpjLimpo.'/compras/'.$ano.'/'.$sequencial.'/arquivos',
            $this->baseUrl.'/v1/orgaos/'.$cnpjLimpo.'/compras/'.$ano.'/'.$sequencial.'/arquivos',
        ];

        $lastStatus = null;
        foreach ($paths as $url) {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($url);

            $lastStatus = $response->status();

            if ($response->successful()) {
                $json = $response->json();

                return is_array($json) ? $json : [];
            }

            if ($response->status() !== 404) {
                Log::warning('PNCP listar arquivos falhou', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                ]);
                throw new RuntimeException(
                    $response->json('message')
                    ?? 'Não foi possível carregar os arquivos desta compra no PNCP.'
                );
            }
        }

        Log::warning('PNCP listar arquivos: 404 em todas as bases tentadas', [
            'cnpj' => $cnpjLimpo,
            'ano' => $ano,
            'sequencial' => $sequencial,
            'last_status' => $lastStatus,
        ]);
        throw new RuntimeException('Lista de arquivos não encontrada no PNCP para esta compra.');
    }

    /**
     * Dados cadastrais do órgão no PNCP (API pública).
     *
     * @return array<string, mixed>
     */
    public function consultarOrgao(string $cnpj): array
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($cnpjLimpo) !== 14) {
            throw new RuntimeException('CNPJ do órgão inválido.');
        }

        return $this->getJsonFirstSuccessful([
            $this->integracaoBaseUrl.'/v1/orgaos/'.$cnpjLimpo,
            $this->baseUrl.'/v1/orgaos/'.$cnpjLimpo,
        ], 'orgão');
    }

    /**
     * Unidades vinculadas ao órgão (com endereço, UASG/código quando existir).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarUnidadesOrgao(string $cnpj): array
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($cnpjLimpo) !== 14) {
            throw new RuntimeException('CNPJ do órgão inválido.');
        }

        $json = $this->getJsonFirstSuccessful([
            $this->integracaoBaseUrl.'/v1/orgaos/'.$cnpjLimpo.'/unidades',
            $this->baseUrl.'/v1/orgaos/'.$cnpjLimpo.'/unidades',
        ], 'unidades do órgão');

        if ($json === []) {
            return [];
        }

        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return is_array($json) && array_is_list($json) ? $json : [];
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, mixed>
     */
    private function getJsonFirstSuccessful(array $urls, string $recurso): array
    {
        $lastStatus = null;
        foreach ($urls as $url) {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($url);

            $lastStatus = $response->status();

            if ($response->successful()) {
                $json = $response->json();

                return is_array($json) ? $json : [];
            }

            if ($response->status() !== 404) {
                Log::warning('PNCP consulta falhou', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'recurso' => $recurso,
                ]);
                throw new RuntimeException(
                    $response->json('message')
                    ?? 'Não foi possível consultar '.$recurso.' no PNCP.'
                );
            }
        }

        Log::warning('PNCP: 404 em todas as bases', ['urls' => $urls, 'recurso' => $recurso, 'last_status' => $lastStatus]);
        throw new RuntimeException('Recurso não encontrado no PNCP: '.$recurso.'.');
    }

    private function toPncpDate(string $ymd): string
    {
        $digits = preg_replace('/\D/', '', $ymd) ?? '';
        if (strlen($digits) !== 8) {
            throw new RuntimeException('Datas devem estar no formato Y-m-d (convertidas para AAAAMMDD).');
        }

        return $digits;
    }
}
