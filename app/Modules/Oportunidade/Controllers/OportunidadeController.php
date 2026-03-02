<?php

namespace App\Modules\Oportunidade\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Oportunidade\Models\Oportunidade;
use App\Modules\Oportunidade\Models\OportunidadeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OportunidadeController extends BaseApiController
{
    use HasAuthContext;

    /**
     * Lista oportunidades (rascunhos de processos) da empresa ativa.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();

            $oportunidades = Oportunidade::query()
                ->with('itens')
                ->where('empresa_id', $empresa->id)
                ->orderByDesc('criado_em')
                ->get();

            return response()->json([
                'data' => $oportunidades,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar oportunidades');
        }
    }

    /**
     * Detalha uma oportunidade específica da empresa ativa.
     *
     * Aceita tanto o ID numérico quanto o campo "numero" (ex: 23/2026, ADA-01).
     */
    public function show(string $id): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();

            $query = Oportunidade::query()
                ->with('itens')
                ->where('empresa_id', $empresa->id);

            if (is_numeric($id)) {
                $oportunidade = $query->where('id', (int) $id)->first();
            } else {
                $oportunidade = $query->where('numero', $id)->first();
            }

            if (!$oportunidade) {
                return response()->json([
                    'message' => 'Oportunidade não encontrada.',
                ], 404);
            }

            return response()->json([
                'data' => $oportunidade,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar oportunidade');
        }
    }

    /**
     * Cria uma nova oportunidade com seus itens.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();

            $data = $request->validate([
                'modalidade' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:255',
                'objeto_resumido' => 'nullable|string',
                'link_oportunidade' => 'nullable|string|max:2048',
                'status' => 'nullable|string|in:rascunho,convertida',
                'itens' => 'nullable|array',
                'itens.*.numero_orcamento' => 'nullable|string|max:50',
                'itens.*.quantidade' => 'nullable|numeric',
                'itens.*.unidade' => 'nullable|string|max:50',
                'itens.*.especificacao' => 'nullable|string',
                'itens.*.endereco_entrega' => 'nullable|string',
                'itens.*.valor_estimado' => 'nullable|numeric',
                'itens.*.produto_atende' => 'nullable|string',
                'itens.*.fornecedor' => 'nullable|string',
                'itens.*.link_produto' => 'nullable|string|max:2048',
                'itens.*.link_catalogo' => 'nullable|string|max:2048',
                'itens.*.custo_frete' => 'nullable|numeric',
            ]);

            $status = $data['status'] ?? 'rascunho';

            return DB::transaction(function () use ($empresa, $data, $status) {
                $oportunidade = new Oportunidade();
                $oportunidade->fill([
                    'empresa_id' => $empresa->id,
                    'modalidade' => $data['modalidade'] ?? null,
                    'numero' => $data['numero'] ?? null,
                    'objeto_resumido' => $data['objeto_resumido'] ?? null,
                    'link_oportunidade' => $data['link_oportunidade'] ?? null,
                    'status' => $status,
                ]);
                $oportunidade->save();

                $itens = $data['itens'] ?? [];

                foreach ($itens as $item) {
                    $oportunidade->itens()->create([
                        'empresa_id' => $empresa->id,
                        'numero_orcamento' => $item['numero_orcamento'] ?? null,
                        'quantidade' => $item['quantidade'] ?? null,
                        'unidade' => $item['unidade'] ?? null,
                        'especificacao' => $item['especificacao'] ?? null,
                        'endereco_entrega' => $item['endereco_entrega'] ?? null,
                        'valor_estimado' => $item['valor_estimado'] ?? null,
                        'produto_atende' => $item['produto_atende'] ?? null,
                        'fornecedor' => $item['fornecedor'] ?? null,
                        'link_produto' => $item['link_produto'] ?? null,
                        'link_catalogo' => $item['link_catalogo'] ?? null,
                        'custo_frete' => $item['custo_frete'] ?? null,
                    ]);
                }

                $oportunidade->load('itens');

                return response()->json([
                    'data' => $oportunidade,
                ], 201);
            });
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao salvar oportunidade');
        }
    }
}

