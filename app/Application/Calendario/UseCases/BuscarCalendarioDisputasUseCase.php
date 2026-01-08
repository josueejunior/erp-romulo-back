<?php

declare(strict_types=1);

namespace App\Application\Calendario\UseCases;

use App\Application\Calendario\DTOs\BuscarCalendarioDisputasDTO;
use App\Application\Calendario\DTOs\CalendarioEventoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo;
use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para buscar calendário de disputas
 * 
 * Fluxo:
 * 1. Verificar cache Redis
 * 2. Buscar processos em participação com data no período
 * 3. Buscar processos em participação sem data (ou com status especial)
 * 4. Combinar e formatar eventos
 * 5. Salvar no cache
 * 6. Retornar Collection de CalendarioEventoDTO
 */
final class BuscarCalendarioDisputasUseCase
{
    private const CACHE_TTL = 1800; // 30 minutos

    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executa o use case
     * 
     * @return Collection<CalendarioEventoDTO>
     */
    public function executar(BuscarCalendarioDisputasDTO $dto, ?int $tenantId = null): Collection
    {
        Log::debug('BuscarCalendarioDisputasUseCase::executar - Iniciando', [
            'empresa_id' => $dto->empresaId,
            'data_inicio' => $dto->getDataInicioFormatted(),
            'data_fim' => $dto->getDataFimFormatted(),
            'tenant_id' => $tenantId,
        ]);

        // 1. Tentar cache primeiro
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = $dto->getCacheKey($tenantId);
            $cached = RedisService::get($cacheKey);
            
            if ($cached !== null) {
                Log::debug('BuscarCalendarioDisputasUseCase - Cache HIT', [
                    'cache_key' => $cacheKey,
                    'count' => count($cached),
                ]);
                
                return collect($cached)->map(fn($item) => CalendarioEventoDTO::fromArray($item));
            }
        }

        // 2. Buscar processos
        $eventos = $this->buscarEventos($dto);

        // 3. Salvar no cache
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = $dto->getCacheKey($tenantId);
            $arrayParaCache = $eventos->map(fn(CalendarioEventoDTO $e) => $e->toArray())->toArray();
            RedisService::set($cacheKey, $arrayParaCache, self::CACHE_TTL);
            
            Log::debug('BuscarCalendarioDisputasUseCase - Cache SET', [
                'cache_key' => $cacheKey,
                'count' => count($arrayParaCache),
            ]);
        }

        Log::debug('BuscarCalendarioDisputasUseCase::executar - Concluído', [
            'total_eventos' => $eventos->count(),
        ]);

        return $eventos;
    }

    /**
     * Busca eventos do calendário
     */
    private function buscarEventos(BuscarCalendarioDisputasDTO $dto): Collection
    {
        $with = [
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->with([
                    'orcamentos.formacaoPreco',
                    'orcamentos.fornecedor',
                    'orcamentoItens.orcamento.fornecedor',
                    'orcamentoItens.formacaoPreco'
                ]);
            }
        ];

        // Buscar processos com data de sessão dentro do período
        $filtrosComData = [
            'empresa_id' => $dto->empresaId,
            'status' => 'participacao',
            'data_hora_sessao_publica_inicio' => $dto->getDataInicioFormatted(),
            'data_hora_sessao_publica_fim' => $dto->getDataFimFormatted(),
        ];
        
        Log::debug('BuscarCalendarioDisputasUseCase - Buscando processos com data', $filtrosComData);
        
        $processosComData = $this->processoRepository->buscarModelosComFiltros($filtrosComData, $with);
        
        Log::debug('BuscarCalendarioDisputasUseCase - Processos com data encontrados', [
            'count' => $processosComData->count(),
        ]);
        
        // Buscar TODOS os processos em participação (incluindo os sem data)
        $filtrosSemData = [
            'empresa_id' => $dto->empresaId,
            'status' => 'participacao',
        ];
        
        $todosProcessosParticipacao = $this->processoRepository->buscarModelosComFiltros($filtrosSemData, $with);
        
        Log::debug('BuscarCalendarioDisputasUseCase - Total processos em participação', [
            'count' => $todosProcessosParticipacao->count(),
        ]);
        
        // Filtrar apenas os que não têm data de sessão ou têm status especial
        $processosSemDataOuPendentes = $todosProcessosParticipacao->filter(function ($processo) {
            return $processo->data_hora_sessao_publica === null 
                || in_array($processo->status_participacao, ['adiado', 'suspenso', 'cancelado']);
        });
        
        // Combinar e remover duplicatas
        $processos = $processosComData->merge($processosSemDataOuPendentes)->unique('id');

        Log::debug('BuscarCalendarioDisputasUseCase - Total processos combinados', [
            'count' => $processos->count(),
        ]);

        // Formatar como DTOs
        return $processos->map(function ($processo) {
            return $this->formatarEvento($processo);
        });
    }

    /**
     * Formata um processo como evento do calendário
     */
    private function formatarEvento(Processo $processo): CalendarioEventoDTO
    {
        $precosMinimos = $this->calcularPrecosMinimos($processo);
        $avisos = $this->gerarAvisos($processo);
        
        $diasRestantes = $processo->data_hora_sessao_publica 
            ? Carbon::now()->diffInDays($processo->data_hora_sessao_publica, false) 
            : null;

        return new CalendarioEventoDTO(
            id: $processo->id,
            identificador: $processo->identificador,
            modalidade: $processo->modalidade,
            numeroModalidade: $processo->numero_modalidade,
            orgao: $processo->orgao?->razao_social,
            uasg: $processo->orgao?->uasg,
            setor: $processo->setor?->nome,
            dataHoraSessao: $processo->data_hora_sessao_publica?->toIso8601String(),
            horarioSessao: $processo->horario_sessao_publica,
            objetoResumido: $processo->objeto_resumido,
            linkEdital: $processo->link_edital,
            portal: $processo->portal,
            precosMinimos: $precosMinimos,
            totalItens: $processo->itens->count(),
            diasRestantes: $diasRestantes !== null ? (int) $diasRestantes : null,
            statusParticipacao: $processo->status_participacao ?? 'normal',
            avisos: $avisos,
        );
    }

    /**
     * Calcula preços mínimos de venda para cada item
     */
    private function calcularPrecosMinimos(Processo $processo): array
    {
        $precos = [];

        foreach ($processo->itens as $item) {
            // Tentar buscar na estrutura antiga (compatibilidade)
            $orcamentoEscolhido = $item->orcamentos->firstWhere('fornecedor_escolhido', true);
            $formacao = null;
            $fornecedorNome = null;

            if ($orcamentoEscolhido?->formacaoPreco) {
                $formacao = $orcamentoEscolhido->formacaoPreco;
                $fornecedorNome = $orcamentoEscolhido->fornecedor->razao_social ?? 'N/A';
            } else {
                // Buscar na nova estrutura (orcamento_itens)
                $orcamentoItemEscolhido = $item->orcamentoItens->firstWhere('fornecedor_escolhido', true);
                if ($orcamentoItemEscolhido) {
                    $formacao = $orcamentoItemEscolhido->formacaoPreco;
                    $fornecedorNome = $orcamentoItemEscolhido->orcamento->fornecedor->razao_social ?? 'N/A';
                }
            }

            if ($formacao) {
                $precos[] = [
                    'item_numero' => $item->numero_item,
                    'descricao' => substr($item->especificacao_tecnica ?? '', 0, 50) . '...',
                    'preco_minimo' => $formacao->preco_minimo,
                    'preco_recomendado' => $formacao->preco_recomendado,
                    'fornecedor' => $fornecedorNome,
                ];
            } else {
                // Usar valor estimado como fallback
                $precos[] = [
                    'item_numero' => $item->numero_item,
                    'descricao' => substr($item->especificacao_tecnica ?? '', 0, 50) . '...',
                    'preco_minimo' => $item->valor_estimado,
                    'preco_recomendado' => null,
                    'fornecedor' => 'Nenhum orçamento escolhido',
                ];
            }
        }

        return $precos;
    }

    /**
     * Gera avisos para o processo na disputa
     */
    private function gerarAvisos(Processo $processo): array
    {
        $avisos = [];
        $hoje = Carbon::now();

        // Aviso de proximidade
        if ($processo->data_hora_sessao_publica) {
            $dataSessao = Carbon::parse($processo->data_hora_sessao_publica);
            $diasRestantes = $hoje->diffInDays($dataSessao, false);
            
            if ($diasRestantes <= 3 && $diasRestantes >= 0) {
                $avisos[] = [
                    'tipo' => 'proximidade',
                    'mensagem' => "Disputa em {$diasRestantes} dia(s)",
                    'prioridade' => $diasRestantes == 0 ? 'alta' : 'media',
                ];
            }
        }

        // Aviso de documentos vencidos
        $documentosVencidos = $processo->documentos()
            ->whereHas('documentoHabilitacao', function ($query) {
                $query->where('data_validade', '<', now())
                    ->where('ativo', true);
            })
            ->count();

        if ($documentosVencidos > 0) {
            $avisos[] = [
                'tipo' => 'documentos',
                'mensagem' => "{$documentosVencidos} documento(s) vencido(s)",
                'prioridade' => 'alta',
            ];
        }

        // Aviso de itens sem orçamento escolhido
        $itensSemOrcamento = $processo->itens()
            ->whereDoesntHave('orcamentos', function ($query) {
                $query->where('orcamentos.fornecedor_escolhido', true);
            })
            ->count();

        if ($itensSemOrcamento > 0) {
            $avisos[] = [
                'tipo' => 'orcamentos',
                'mensagem' => "{$itensSemOrcamento} item(ns) sem orçamento escolhido",
                'prioridade' => 'media',
            ];
        }

        // Aviso de itens sem formação de preço
        $itensSemFormacao = $processo->itens()
            ->whereHas('orcamentos', function ($query) {
                $query->where('orcamentos.fornecedor_escolhido', true);
            })
            ->whereDoesntHave('formacoesPreco')
            ->count();

        if ($itensSemFormacao > 0) {
            $avisos[] = [
                'tipo' => 'formacao_preco',
                'mensagem' => "{$itensSemFormacao} item(ns) sem formação de preço",
                'prioridade' => 'alta',
            ];
        }

        return $avisos;
    }
}

