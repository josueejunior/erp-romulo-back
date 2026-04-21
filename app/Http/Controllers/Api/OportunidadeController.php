<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Oportunidade;
use App\Modules\Processo\Models\Processo;
use App\Services\Pncp\PncpCompraIdentificador;
use App\Services\Pncp\PncpConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OportunidadeController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $rows = Oportunidade::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Oportunidade $o) => $this->serialize($o));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(int $id): JsonResponse
    {
        $o = Oportunidade::query()->whereKey($id)->first();
        if (!$o) {
            return response()->json(['success' => false, 'message' => 'Oportunidade não encontrada.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->serialize($o)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'modalidade' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:255'],
            'objeto_resumido' => ['nullable', 'string'],
            'link_oportunidade' => ['nullable', 'string', 'max:2048'],
            'itens' => ['nullable', 'array'],
            'itens.*.numero_orcamento' => ['nullable', 'string', 'max:255'],
            'itens.*.quantidade' => ['nullable', 'numeric'],
            'itens.*.unidade' => ['nullable', 'string', 'max:50'],
            'itens.*.especificacao' => ['nullable', 'string'],
            'itens.*.endereco_entrega' => ['nullable', 'string'],
            'itens.*.valor_estimado' => ['nullable', 'numeric'],
            'itens.*.produto_atende' => ['nullable', 'string'],
            'itens.*.fornecedor' => ['nullable', 'string'],
            'itens.*.fornecedor_id' => ['nullable', 'integer'],
            'itens.*.link_produto' => ['nullable', 'string', 'max:2048'],
            'itens.*.link_catalogo' => ['nullable', 'string', 'max:2048'],
            'itens.*.custo_frete' => ['nullable', 'numeric'],
            'pncp_numero_controle' => ['nullable', 'string', 'max:120'],
            'pncp_snapshot' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $data = $validator->validated();

        $o = new Oportunidade;
        $o->empresa_id = $empresa->id;
        $o->modalidade = $data['modalidade'] ?? null;
        $o->numero = $data['numero'] ?? null;
        $o->objeto_resumido = $data['objeto_resumido'] ?? null;
        $o->link_oportunidade = $data['link_oportunidade'] ?? null;
        $o->itens = $data['itens'] ?? [];
        $o->pncp_numero_controle = $data['pncp_numero_controle'] ?? null;
        $o->pncp_snapshot = $data['pncp_snapshot'] ?? null;
        $o->save();

        return response()->json(['success' => true, 'data' => $this->serialize($o)], 201);
    }

    /**
     * Proxy seguro para PNCP: contratações por data de publicação.
     *
     * Query: data_inicial, data_final (Y-m-d), codigo_modalidade (1–14),
     * pagina?, tamanho_pagina? (>=10), uf?, codigo_ibge?, cnpj?, texto? (filtro local)
     */
    public function pncpPublicacoes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'data_inicial' => ['required', 'date_format:Y-m-d'],
            'data_final' => ['required', 'date_format:Y-m-d', 'after_or_equal:data_inicial'],
            'codigo_modalidade' => ['required', 'integer', 'min:1', 'max:14'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'tamanho_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
            'codigo_ibge' => ['nullable', 'string', 'max:12'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'texto' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros inválidos para consulta PNCP.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $q = $validator->validated();

        try {
            $svc = PncpConsultaService::fromConfig();
            $raw = $svc->contratacoesPublicacao([
                'data_inicial' => $q['data_inicial'],
                'data_final' => $q['data_final'],
                'codigo_modalidade' => (int) $q['codigo_modalidade'],
                'pagina' => $q['pagina'] ?? 1,
                'tamanho_pagina' => $q['tamanho_pagina'] ?? 10,
                'uf' => $q['uf'] ?? null,
                'codigo_ibge' => $q['codigo_ibge'] ?? null,
                'cnpj' => $q['cnpj'] ?? null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $lista = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $texto = isset($q['texto']) ? mb_strtolower(trim($q['texto'])) : '';

        if ($texto !== '') {
            $lista = array_values(array_filter($lista, function ($row) use ($texto) {
                if (!is_array($row)) {
                    return false;
                }
                $blob = mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE));

                return str_contains($blob, $texto);
            }));
        }

        $mapped = array_map(fn ($row) => $this->mapPncpRow(is_array($row) ? $row : []), $lista);

        return response()->json([
            'success' => true,
            'data' => $mapped,
            'meta' => [
                'totalRegistros' => $raw['totalRegistros'] ?? null,
                'totalPaginas' => $raw['totalPaginas'] ?? null,
                'numeroPagina' => $raw['numeroPagina'] ?? null,
                'paginasRestantes' => $raw['paginasRestantes'] ?? null,
                'empty' => $raw['empty'] ?? null,
            ],
        ]);
    }

    /**
     * Carrega cabeçalho da compra (API consulta) + itens (API pública PNCP) a partir do
     * número de controle embutido em texto ou URL, ou de cnpj+ano+sequencial.
     *
     * Query: referencia? (string), ou cnpj (14 dígitos) + ano + sequencial
     */
    public function pncpCompra(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'referencia' => ['nullable', 'string', 'max:8192'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'ano' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'sequencial' => ['nullable', 'integer', 'min:1', 'max:9999999'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $q = $validator->validated();
        $ids = $this->resolvePncpCompraIdsFromQuery($q);

        if ($ids === null) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível identificar a compra. Informe o número de controle PNCP (formato 00000000000000-0-000000/AAAA) ou cole um texto/URL que contenha esse código, ou envie cnpj, ano e sequencial.',
            ], 422);
        }

        try {
            $svc = PncpConsultaService::fromConfig();
            $compra = $svc->recuperarCompra($ids['cnpj'], $ids['ano'], $ids['sequencial']);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        if ($compra === []) {
            return response()->json([
                'success' => false,
                'message' => 'Compra não encontrada no PNCP.',
            ], 404);
        }

        $itensRaw = [];
        $itensPnOk = false;
        $itensPnMensagem = null;

        try {
            $itensRaw = $svc->listarItensCompra($ids['cnpj'], $ids['ano'], $ids['sequencial']);
            $itensPnOk = true;
        } catch (Throwable $e) {
            $itensPnMensagem = $e->getMessage();
            Log::warning('PNCP itens não carregados', ['erro' => $itensPnMensagem, 'ids' => $ids]);
        }

        $itensForm = [];
        foreach ($itensRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itensForm[] = $this->mapPncpItemToFormRow($row);
        }

        $numeroControle = $compra['numeroControlePNCP'] ?? null;
        $link = $this->firstNonEmptyString([
            $compra['linkSistemaOrigem'] ?? null,
            $compra['linkProcessoEletronico'] ?? null,
        ]);

        $objeto = isset($compra['objetoCompra']) ? (string) $compra['objetoCompra'] : '';
        $objetoResumido = $objeto !== '' && mb_strlen($objeto) > 2000
            ? mb_substr($objeto, 0, 1997).'…'
            : $objeto;

        $payload = [
            'modalidade' => isset($compra['modalidadeNome']) ? (string) $compra['modalidadeNome'] : null,
            'numero' => $this->firstNonEmptyString([
                $compra['numeroCompra'] ?? null,
                $compra['processo'] ?? null,
                $numeroControle,
            ]),
            'objeto_resumido' => $objetoResumido !== '' ? $objetoResumido : null,
            'link_oportunidade' => is_string($link) && $link !== '' ? $link : null,
            'itens' => $itensForm,
            'pncp_numero_controle' => is_string($numeroControle) ? $numeroControle : null,
            'pncp_snapshot' => [
                'compra' => $compra,
                'itens' => $itensRaw,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $payload,
            'meta' => [
                'cnpj' => $ids['cnpj'],
                'ano' => $ids['ano'],
                'sequencial' => $ids['sequencial'],
                'itens_pn_ok' => $itensPnOk,
                'itens_pn_mensagem' => $itensPnMensagem,
                'itens_count' => count($itensForm),
            ],
        ]);
    }

    /**
     * Lista arquivos publicados da compra no PNCP (metadados + {@code url} de download).
     *
     * Destinado a fluxos pontuais (ex.: envio do PDF do edital a um extrator/LLM), não ao
     * preenchimento geral de oportunidade/processo — evita chamadas extras à API do PNCP.
     *
     * Query: mesmos parâmetros de {@see pncpCompra} ({@code referencia} ou {@code cnpj}+{@code ano}+{@code sequencial}).
     */
    public function pncpCompraArquivos(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'referencia' => ['nullable', 'string', 'max:8192'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'ano' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'sequencial' => ['nullable', 'integer', 'min:1', 'max:9999999'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $q = $validator->validated();
        $ids = $this->resolvePncpCompraIdsFromQuery($q);
        if ($ids === null) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível identificar a compra. Informe o número de controle PNCP ou cnpj, ano e sequencial.',
            ], 422);
        }

        try {
            $svc = PncpConsultaService::fromConfig();
            $arquivos = $svc->listarArquivosCompra($ids['cnpj'], $ids['ano'], $ids['sequencial']);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $normalizados = [];
        foreach ($arquivos as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalizados[] = [
                'url' => isset($row['url']) && is_string($row['url']) ? $row['url'] : null,
                'titulo' => isset($row['titulo']) ? (is_string($row['titulo']) ? $row['titulo'] : null) : null,
                'tipo_documento_id' => isset($row['tipoDocumentoId']) ? (is_numeric($row['tipoDocumentoId']) ? (int) $row['tipoDocumentoId'] : null) : null,
                'tipo_documento_nome' => isset($row['tipoDocumentoNome']) && is_string($row['tipoDocumentoNome'])
                    ? $row['tipoDocumentoNome']
                    : null,
                'sequencial_documento' => isset($row['sequencialDocumento']) && is_numeric($row['sequencialDocumento'])
                    ? (int) $row['sequencialDocumento']
                    : (isset($row['sequencial']) && is_numeric($row['sequencial']) ? (int) $row['sequencial'] : null),
                'data_publicacao_pncp' => isset($row['dataPublicacaoPncp']) && is_string($row['dataPublicacaoPncp'])
                    ? $row['dataPublicacaoPncp']
                    : null,
            ];
        }

        $editalProvaveis = array_values(array_filter($normalizados, function (array $a): bool {
            $tipo = mb_strtolower((string) ($a['tipo_documento_nome'] ?? ''));
            $titulo = mb_strtolower((string) ($a['titulo'] ?? ''));
            $tipoId = isset($a['tipo_documento_id']) ? (int) $a['tipo_documento_id'] : 0;

            return str_contains($tipo, 'edital')
                || str_contains($titulo, 'edital')
                || $tipoId === 2;
        }));

        return response()->json([
            'success' => true,
            'data' => [
                'arquivos' => $normalizados,
                'edital_provavel' => $editalProvaveis,
            ],
            'meta' => [
                'cnpj' => $ids['cnpj'],
                'ano' => $ids['ano'],
                'sequencial' => $ids['sequencial'],
                'total' => count($normalizados),
            ],
        ]);
    }

    /**
     * @param  array<string,mixed>  $q
     * @return array{cnpj:string,ano:int,sequencial:int}|null
     */
    private function resolvePncpCompraIdsFromQuery(array $q): ?array
    {
        return PncpCompraIdentificador::fromQueryParams($q);
    }

    /**
     * @param  array<int,mixed>  $candidates
     */
    private function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (is_string($c)) {
                $t = trim($c);
                if ($t !== '') {
                    return $t;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function mapPncpItemToFormRow(array $item): array
    {
        $n = (int) ($item['numeroItem'] ?? 0);
        if ($n < 1) {
            $n = 1;
        }
        $desc = isset($item['descricao']) ? trim((string) $item['descricao']) : '';
        $descUmaLinha = preg_replace('/\s+/u', ' ', $desc) ?? $desc;
        $produtoResumo = $descUmaLinha !== '' ? mb_substr($descUmaLinha, 0, 280) : '';

        $q = $item['quantidade'] ?? null;
        $quantidadeStr = $q === null || $q === '' ? '' : (is_numeric($q) ? (string) $q : '');

        $vu = $item['valorUnitarioEstimado'] ?? null;
        $valorEstimado = $vu !== null && is_numeric($vu) ? (string) $vu : '';

        return [
            'numeroOrcamento' => 'Item PNCP '.$n,
            'quantidade' => $quantidadeStr,
            'unidade' => isset($item['unidadeMedida']) ? trim((string) $item['unidadeMedida']) : '',
            'especificacao' => $desc,
            'enderecoEntrega' => '',
            'valorEstimado' => $valorEstimado,
            'produtoAtende' => $produtoResumo,
            'fornecedor' => '',
            'linkProduto' => '',
            'linkCatalogo' => '',
            'custoFrete' => '',
        ];
    }

    private function serialize(Oportunidade $o): array
    {
        return [
            'id' => $o->id,
            'modalidade' => $o->modalidade,
            'numero' => $o->numero,
            'objeto_resumido' => $o->objeto_resumido,
            'link_oportunidade' => $o->link_oportunidade,
            'itens' => $o->itens ?? [],
            'pncp_numero_controle' => $o->pncp_numero_controle,
            'pncp_snapshot' => $o->pncp_snapshot,
            'criado_em' => $o->criado_em?->toIso8601String(),
            'atualizado_em' => $o->atualizado_em?->toIso8601String(),
        ];
    }

    private function mapPncpRow(array $row): array
    {
        $orgao = is_array($row['orgaoEntidade'] ?? null) ? $row['orgaoEntidade'] : [];
        $unidade = is_array($row['unidadeOrgao'] ?? null) ? $row['unidadeOrgao'] : [];
        $processoInterno = $this->findProcessoInterno($row);

        return [
            'numero_controle_pncp' => $row['numeroControlePNCP'] ?? null,
            'modalidade_nome' => $row['modalidadeNome'] ?? null,
            'numero_compra' => $row['numeroCompra'] ?? null,
            'processo' => $row['processo'] ?? null,
            'objeto' => $row['objetoCompra'] ?? null,
            'orgao_razao_social' => $orgao['razaoSocial'] ?? null,
            'orgao_cnpj' => $orgao['cnpj'] ?? null,
            'uf' => $unidade['ufSigla'] ?? null,
            'municipio' => $unidade['municipioNome'] ?? null,
            'valor_total_estimado' => $row['valorTotalEstimado'] ?? null,
            'data_abertura_proposta' => $row['dataAberturaProposta'] ?? null,
            'data_encerramento_proposta' => $row['dataEncerramentoProposta'] ?? null,
            'data_publicacao_pncp' => $row['dataPublicacaoPncp'] ?? null,
            'link' => $row['linkSistemaOrigem'] ?? null,
            'situacao' => $row['situacaoCompraNome'] ?? null,
            'processo_interno' => $processoInterno,
            'ja_cadastrado' => $processoInterno !== null,
        ];
    }

    /**
     * Tenta localizar processo já cadastrado para enriquecer o resultado PNCP.
     *
     * Estratégia de matching (ordem):
     * 1) número da modalidade/compra
     * 2) número do processo administrativo
     * 3) link do edital
     */
    private function findProcessoInterno(array $row): ?array
    {
        $numeroCompra = isset($row['numeroCompra']) ? trim((string) $row['numeroCompra']) : '';
        $numeroProcesso = isset($row['processo']) ? trim((string) $row['processo']) : '';
        $linkSistema = isset($row['linkSistemaOrigem']) ? trim((string) $row['linkSistemaOrigem']) : '';

        $query = Processo::query()
            ->with('orgao:id,razao_social')
            ->select([
                'id',
                'numero_modalidade',
                'numero_processo_administrativo',
                'modalidade',
                'status',
                'objeto_resumido',
                'data_hora_sessao_publica',
                'link_edital',
                'orgao_id',
            ]);

        if ($numeroCompra !== '') {
            $query->where('numero_modalidade', $numeroCompra);
        } elseif ($numeroProcesso !== '') {
            $query->where('numero_processo_administrativo', $numeroProcesso);
        } elseif ($linkSistema !== '') {
            $query->where('link_edital', $linkSistema);
        } else {
            return null;
        }

        $processo = $query->first();
        if (!$processo) {
            return null;
        }

        return [
            'id' => $processo->id,
            'modalidade' => $processo->modalidade,
            'numero_modalidade' => $processo->numero_modalidade,
            'numero_processo_administrativo' => $processo->numero_processo_administrativo,
            'status' => $processo->status,
            'objeto_resumido' => $processo->objeto_resumido,
            'data_hora_sessao_publica' => $processo->data_hora_sessao_publica?->toIso8601String(),
            'link_edital' => $processo->link_edital,
            'orgao' => $processo->orgao?->razao_social,
        ];
    }
}
