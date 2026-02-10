<?php

namespace App\Modules\Calendario\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\ProcessoStatusService;
use Illuminate\Http\Request;

class CalendarioDisputasController extends BaseApiController
{
    protected ProcessoStatusService $statusService;

    public function __construct(ProcessoStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Retorna processos para o calendário de disputas
     * Inclui preço mínimo por item
     */
    public function index(Request $request)
    {
        // Verificar se o plano tem acesso a calendários
        $tenant = tenancy()->tenant;
        if (!$tenant || !$tenant->temAcessoCalendario()) {
            return response()->json([
                'message' => 'O calendário não está disponível no seu plano. Faça upgrade para o plano Profissional ou superior.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        $query = Processo::where('empresa_id', $empresa->id)
            ->whereIn('status', ['participacao', 'julgamento_habilitacao'])
            ->with([
                'orgao:id,uasg,razao_social',
                'setor:id,orgao_id,nome',
                'itens' => function($query) {
                    $query->select('id', 'processo_id', 'numero_item', 'especificacao_tecnica', 'quantidade', 'unidade')
                        ->with(['formacoesPreco' => function($q) {
                            $q->select('id', 'processo_item_id', 'preco_minimo')
                              ->orderBy('preco_minimo', 'asc');
                        }]);
                }
            ]);

        // 🔥 CORREÇÃO: Filtrar por período, mas incluir processos sem data também
        // Processos com status "participacao" devem aparecer mesmo sem data definida
        if ($request->data_inicio || $request->data_fim) {
            $query->where(function($q) use ($request) {
                // Processos com data no período
                if ($request->data_inicio && $request->data_fim) {
                    $q->whereBetween('data_hora_sessao_publica', [$request->data_inicio, $request->data_fim]);
                } elseif ($request->data_inicio) {
                    $q->where('data_hora_sessao_publica', '>=', $request->data_inicio);
                } elseif ($request->data_fim) {
                    $q->where('data_hora_sessao_publica', '<=', $request->data_fim);
                }
                
                // OU processos sem data (especialmente para status "participacao")
                $q->orWhereNull('data_hora_sessao_publica');
            });
        }

        // Filtrar por status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $processos = $query->orderBy('data_hora_sessao_publica', 'asc')->get();

        // Formatar resposta com preço mínimo por item
        $processosFormatados = $processos->map(function($processo) {
            $itens = $processo->itens->map(function($item) {
                // Pegar menor preço mínimo das formações de preço
                $precoMinimo = $item->formacoesPreco->min('preco_minimo') ?? $item->valor_estimado;

                return [
                    'id' => $item->id,
                    'numero_item' => $item->numero_item,
                    'especificacao_tecnica' => $item->especificacao_tecnica,
                    'quantidade' => $item->quantidade,
                    'unidade' => $item->unidade,
                    'preco_minimo' => $precoMinimo,
                    'valor_estimado' => $item->valor_estimado,
                ];
            });

            // Verificar se deve sugerir mudança de status
            $sugerirJulgamento = $this->statusService->deveSugerirJulgamento($processo);

            return [
                'id' => $processo->id,
                'identificador' => $processo->identificador,
                'numero_modalidade' => $processo->numero_modalidade,
                'modalidade' => $processo->modalidade,
                'status' => $processo->status,
                'data_hora_sessao_publica' => $processo->data_hora_sessao_publica?->format('Y-m-d H:i:s'),
                'data_hora_sessao_publica_formatted' => $processo->data_hora_sessao_publica?->format('d/m/Y H:i'),
                'orgao' => [
                    'id' => $processo->orgao->id,
                    'uasg' => $processo->orgao->uasg,
                    'razao_social' => $processo->orgao->razao_social,
                ],
                'setor' => [
                    'id' => $processo->setor->id,
                    'nome' => $processo->setor->nome,
                ],
                'objeto_resumido' => $processo->objeto_resumido,
                'itens' => $itens,
                'sugerir_julgamento' => $sugerirJulgamento,
            ];
        });

        return response()->json([
            'processos' => $processosFormatados,
            'total' => $processosFormatados->count(),
        ]);
    }

    /**
     * Retorna eventos do calendário em formato de eventos
     */
    public function eventos(Request $request)
    {
        // Verificar se o plano tem acesso a calendários
        $tenant = tenancy()->tenant;
        if (!$tenant || !$tenant->temAcessoCalendario()) {
            return response()->json([
                'message' => 'O calendário não está disponível no seu plano. Faça upgrade para o plano Profissional ou superior.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Filtrar APENAS processos da empresa ativa (não incluir NULL)
        $query = Processo::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->whereIn('status', ['participacao', 'julgamento_habilitacao'])
            ->with(['orgao:id,uasg,razao_social', 'itens.formacoesPreco']);

        // 🔥 CORREÇÃO: Incluir processos sem data OU com data no período
        // Processos com status "participacao" devem aparecer mesmo sem data definida
        if ($request->data_inicio || $request->data_fim) {
            $query->where(function($q) use ($request) {
                // Processos com data no período
                if ($request->data_inicio && $request->data_fim) {
                    $q->whereBetween('data_hora_sessao_publica', [$request->data_inicio, $request->data_fim]);
                } elseif ($request->data_inicio) {
                    $q->where('data_hora_sessao_publica', '>=', $request->data_inicio);
                } elseif ($request->data_fim) {
                    $q->where('data_hora_sessao_publica', '<=', $request->data_fim);
                }
                
                // OU processos sem data (especialmente para status "participacao")
                $q->orWhereNull('data_hora_sessao_publica');
            });
        }

        $processos = $query->orderBy('data_hora_sessao_publica', 'asc')->get();

        $eventos = $processos->map(function($processo) {
            $dataHora = $processo->data_hora_sessao_publica;
            
            // Calcular menor preço mínimo entre todos os itens
            $precoMinimoTotal = $processo->itens->flatMap(function($item) {
                return $item->formacoesPreco->pluck('preco_minimo');
            })->filter()->min();

            return [
                'id' => $processo->id,
                'title' => $processo->identificador,
                'start' => $dataHora?->format('Y-m-d\TH:i:s'),
                'end' => $dataHora?->addHour()?->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $processo->status === 'participacao' ? '#3b82f6' : '#10b981',
                'borderColor' => $processo->status === 'participacao' ? '#2563eb' : '#059669',
                'extendedProps' => [
                    'processo_id' => $processo->id,
                    'modalidade' => $processo->modalidade,
                    'status' => $processo->status,
                    'orgao' => $processo->orgao->uasg ?? $processo->orgao->razao_social,
                    'preco_minimo' => $precoMinimoTotal,
                    'quantidade_itens' => $processo->itens->count(),
                ],
            ];
        });

        return response()->json($eventos);
    }
}





