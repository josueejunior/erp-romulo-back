<?php

declare(strict_types=1);

namespace App\Application\Calendario\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para buscar avisos urgentes do calendário
 * 
 * Fluxo:
 * 1. Buscar processos em participação nos próximos 3 dias
 * 2. Gerar avisos para cada processo
 * 3. Retornar array de avisos
 */
final class BuscarAvisosUrgentesUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executa o use case
     */
    public function executar(int $empresaId): array
    {
        Log::debug('BuscarAvisosUrgentesUseCase::executar - Iniciando', [
            'empresa_id' => $empresaId,
        ]);

        $hoje = Carbon::now();
        $proximos3Dias = $hoje->copy()->addDays(3);

        $filtros = [
            'empresa_id' => $empresaId,
            'status' => 'participacao',
            'data_hora_sessao_publica_inicio' => $hoje->toDateTimeString(),
            'data_hora_sessao_publica_fim' => $proximos3Dias->toDateTimeString(),
        ];

        $with = ['orgao', 'setor'];

        $processosDisputa = $this->processoRepository->buscarModelosComFiltros($filtros, $with);

        Log::debug('BuscarAvisosUrgentesUseCase::executar - Processos encontrados', [
            'count' => $processosDisputa->count(),
        ]);

        $avisos = [];

        foreach ($processosDisputa as $processo) {
            $avisosProcesso = $this->gerarAvisosDisputa($processo);
            if (!empty($avisosProcesso)) {
                $avisos[] = [
                    'processo_id' => $processo->id,
                    'processo_identificador' => $processo->identificador,
                    'data_sessao' => $processo->data_hora_sessao_publica,
                    'avisos' => $avisosProcesso,
                ];
            }
        }

        Log::debug('BuscarAvisosUrgentesUseCase::executar - Concluído', [
            'total_avisos' => count($avisos),
        ]);

        return $avisos;
    }

    /**
     * Gera avisos para o processo na disputa
     */
    private function gerarAvisosDisputa($processo): array
    {
        $avisos = [];
        $hoje = Carbon::now();
        $dataSessao = Carbon::parse($processo->data_hora_sessao_publica);

        // Aviso de proximidade
        $diasRestantes = $hoje->diffInDays($dataSessao, false);
        if ($diasRestantes <= 3 && $diasRestantes >= 0) {
            $avisos[] = [
                'tipo' => 'proximidade',
                'mensagem' => "Disputa em {$diasRestantes} dia(s)",
                'prioridade' => $diasRestantes == 0 ? 'alta' : 'media',
            ];
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

