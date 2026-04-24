<?php

declare(strict_types=1);

namespace App\Services\Pncp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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

        return new self($base, $integracao, (int) config('pncp.timeout_seconds', 90));
    }

    /**
     * Cliente HTTP padronizado para o PNCP (timeout total + conexão + User-Agent).
     *
     * @param  int|null  $timeoutSeconds  null = usa {@see $this->timeoutSeconds}
     */
    private function pncpHttp(?int $timeoutSeconds = null): \Illuminate\Http\Client\PendingRequest
    {
        $t = $timeoutSeconds ?? $this->timeoutSeconds;

        return Http::timeout($t)
            ->connectTimeout((int) config('pncp.connect_timeout_seconds', 25))
            ->withHeaders([
                'User-Agent' => 'AddSimp/1.0 (+https://addsimp.com; consulta PNCP)',
            ])
            ->acceptJson();
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
            // Manual PNCP API Consultas v1: parâmetro GET é codigoMunicipioIbge (não codigoIbge).
            $query['codigoMunicipioIbge'] = preg_replace('/\D/', '', (string) $params['codigo_ibge']);
        }
        if (!empty($params['cnpj'])) {
            $query['cnpj'] = preg_replace('/\D/', '', (string) $params['cnpj']);
        }

        $url = $this->baseUrl.'/v1/contratacoes/publicacao';

        $maxAttempts = (int) config('pncp.retry_attempts', 5);
        $baseSleepMs = (int) config('pncp.retry_base_sleep_ms', 600);
        $deadline = microtime(true) + (float) config('pncp.publicacao_max_total_seconds', 95);
        $attemptTimeoutCfg = (int) config('pncp.publicacao_attempt_timeout_seconds', 42);
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $remainingSec = $deadline - microtime(true);
            if ($remainingSec < 2.0) {
                throw new RuntimeException(
                    'Tempo máximo de consulta ao PNCP esgotado. Reduza o intervalo de datas, a UF ou tente novamente em instantes.'
                );
            }

            $attemptTimeout = min($attemptTimeoutCfg, max(8, (int) floor($remainingSec) - 2));

            try {
                $lastResponse = $this->pncpHttp($attemptTimeout)->get($url, $query);
            } catch (ConnectionException $e) {
                Log::warning('PNCP contratacoes/publicacao: falha de conexão', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);
                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException(
                        'Não foi possível conectar ao PNCP. Verifique a rede ou tente novamente em instantes.'
                    );
                }
                $this->pncpSleepBackoff($attempt, $baseSleepMs, $deadline);

                continue;
            }

            if ($lastResponse->successful()) {
                return $lastResponse->json() ?? ['data' => []];
            }

            $status = $lastResponse->status();
            $retryableHttp = in_array($status, [408, 425, 429, 500, 502, 503, 504], true) && $attempt < $maxAttempts;

            if ($retryableHttp) {
                Log::info('PNCP contratacoes/publicacao: retentativa após HTTP '.$status, [
                    'attempt' => $attempt,
                    'query' => $query,
                ]);
                // Respostas grandes: reduzir página na metade a partir da 3ª tentativa (mín. 10).
                if ($attempt >= 3 && ($status === 502 || $status === 504)) {
                    $query['tamanhoPagina'] = max(10, (int) floor($query['tamanhoPagina'] / 2));
                }
                $this->pncpSleepBackoff($attempt, $baseSleepMs, $deadline);

                continue;
            }

            Log::warning('PNCP consulta falhou', [
                'status' => $status,
                'body' => $lastResponse->body(),
                'query' => $query,
            ]);
            throw new RuntimeException($this->mensagemCorpoErroPncp(
                $lastResponse,
                'Tente outro intervalo, UF ou modalidade; o serviço do governo pode estar instável.'
            ));
        }

        throw new RuntimeException('PNCP: resposta inesperada ao consultar publicações.');
    }

    private function pncpSleepBackoff(int $attempt, int $baseSleepMs, float $deadline): void
    {
        $remainingUs = (int) (($deadline - microtime(true)) * 1_000_000);
        if ($remainingUs < 50_000) {
            return;
        }
        $jitter = random_int(0, (int) max(1, $baseSleepMs / 4));
        $ms = ($baseSleepMs * $attempt) + $jitter;
        $sleepUs = min($remainingUs - 40_000, min(3_000_000, $ms * 1000));
        if ($sleepUs > 0) {
            usleep($sleepUs);
        }
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

        $response = $this->pncpHttp()->get($url);

        if ($response->status() === 204) {
            return [];
        }

        if (!$response->successful()) {
            Log::warning('PNCP recuperar compra falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException($this->mensagemCorpoErroPncp(
                $response,
                'Compra não encontrada no PNCP para o identificador informado.'
            ));
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
            $response = $this->pncpHttp()->get($url);

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
                throw new RuntimeException($this->mensagemCorpoErroPncp(
                    $response,
                    'Não foi possível carregar os itens desta compra no PNCP.'
                ));
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
            $response = $this->pncpHttp()->get($url);

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
                throw new RuntimeException($this->mensagemCorpoErroPncp(
                    $response,
                    'Não foi possível carregar os arquivos desta compra no PNCP.'
                ));
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
            $response = $this->pncpHttp()->get($url);

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
                throw new RuntimeException($this->mensagemCorpoErroPncp(
                    $response,
                    'Não foi possível consultar '.$recurso.' no PNCP.'
                ));
            }
        }

        Log::warning('PNCP: 404 em todas as bases', ['urls' => $urls, 'recurso' => $recurso, 'last_status' => $lastStatus]);
        throw new RuntimeException('Recurso não encontrado no PNCP: '.$recurso.'.');
    }

    /**
     * Texto legível a partir da resposta de erro do PNCP (campos variam conforme endpoint / versão da API).
     */
    private function mensagemCorpoErroPncp(Response $response, string $fallback): string
    {
        $status = $response->status();
        $json = $response->json();

        if (is_array($json)) {
            foreach (['message', 'detail', 'title', 'error', 'mensagem'] as $key) {
                $v = $json[$key] ?? null;
                if (is_string($v)) {
                    $t = trim($v);
                    if ($t !== '') {
                        return 'PNCP (HTTP '.$status.'): '.$t;
                    }
                }
            }

            if (isset($json['errors']) && is_array($json['errors'])) {
                $partes = [];
                foreach ($json['errors'] as $msgs) {
                    if (is_array($msgs)) {
                        foreach ($msgs as $m) {
                            if (is_string($m) && trim($m) !== '') {
                                $partes[] = trim($m);
                            }
                        }
                    } elseif (is_string($msgs) && trim($msgs) !== '') {
                        $partes[] = trim($msgs);
                    }
                }
                if ($partes !== []) {
                    return 'PNCP (HTTP '.$status.'): '.implode(' ', array_slice($partes, 0, 5));
                }
            }
        }

        $raw = (string) $response->body();
        $snippet = mb_substr(preg_replace('/\s+/u', ' ', strip_tags($raw)), 0, 220);
        $snippet = trim($snippet);

        if ($snippet !== '') {
            return 'PNCP (HTTP '.$status.'): '.$snippet;
        }

        return 'PNCP retornou HTTP '.$status.'. '.$fallback;
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
