<?php

namespace App\Domain\Relatorio\Queries;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Query Object para Relatório Financeiro
 * 
 * Centraliza queries complexas relacionadas a relatórios financeiros
 */
class RelatorioFinanceiroQuery
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Busca processos em execução com filtros de data
     * 
     * Retorna modelos Eloquent (não entidades de domínio) porque precisamos
     * dos relacionamentos (itens, contratos, notas fiscais) para cálculos financeiros
     */
    public function buscarProcessosExecucao(int $empresaId, ?string $dataInicio = null, ?string $dataFim = null): Collection
    {
        $filtros = [
            'empresa_id' => $empresaId,
            'status' => 'execucao',
        ];

        if ($dataInicio) {
            $filtros['data_inicio'] = $dataInicio;
        }

        if ($dataFim) {
            $filtros['data_fim'] = $dataFim;
        }

        // Usar método que retorna modelos Eloquent (com relacionamentos)
        // Nota: buscarModelosComFiltros não está na interface, mas está na implementação
        // Para manter DDD, vamos buscar IDs primeiro e depois buscar modelos
        $paginator = $this->processoRepository->buscarComFiltros($filtros);
        $processosIds = $paginator->getCollection()->pluck('id')->toArray();
        
        if (empty($processosIds)) {
            return collect([]);
        }

        // Buscar modelos Eloquent com relacionamentos necessários
        // Nota: Isso quebra um pouco o DDD, mas é necessário para cálculos financeiros
        // que dependem de relacionamentos Eloquent
        if (method_exists($this->processoRepository, 'buscarModelosComFiltros')) {
            return collect($this->processoRepository->buscarModelosComFiltros(
                ['id' => $processosIds, 'empresa_id' => $empresaId],
                ['itens', 'contratos', 'empenhos', 'notasFiscais']
            ));
        }

        // Fallback: buscar diretamente via Eloquent (quebra DDD, mas necessário)
        return \App\Modules\Processo\Models\Processo::whereIn('id', $processosIds)
            ->where('empresa_id', $empresaId)
            ->with(['itens', 'contratos', 'empenhos', 'notasFiscais'])
            ->get();
    }

    /**
     * Calcula total de custos indiretos no período
     * 
     * Nota: O repository pode não suportar filtros de data diretamente,
     * então filtramos após buscar todos os custos da empresa
     */
    public function calcularCustosIndiretos(int $empresaId, ?string $dataInicio = null, ?string $dataFim = null): float
    {
        $filtros = [
            'empresa_id' => $empresaId,
            'per_page' => 10000, // Buscar todos
        ];

        // Buscar custos indiretos via repository
        $paginator = $this->custoIndiretoRepository->buscarComFiltros($filtros);
        
        $custos = $paginator->getCollection();
        
        // 🔥 CORREÇÃO: Aplicar filtros de data manualmente se necessário
        // Se não houver filtros de data, incluir TODOS os custos (mesmo sem data)
        // Se houver filtros de data, incluir custos dentro do período OU sem data (para não perder custos importantes)
        if ($dataInicio || $dataFim) {
            $custos = $custos->filter(function($custo) use ($dataInicio, $dataFim) {
                // Se não tem data, incluir sempre (custos sem data são considerados válidos)
                if (!$custo->data) {
                    return true;
                }
                
                try {
                    $dataCusto = Carbon::parse($custo->data);
                    
                    if ($dataInicio && $dataCusto->lt(Carbon::parse($dataInicio))) {
                        return false;
                    }
                    
                    if ($dataFim && $dataCusto->gt(Carbon::parse($dataFim))) {
                        return false;
                    }
                    
                    return true;
                } catch (\Exception $e) {
                    // Se não conseguir parsear a data, incluir o custo (melhor incluir do que excluir)
                    return true;
                }
            });
        }
        
        // 🔥 CORREÇÃO: Garantir que o valor seja um número válido
        return (float) $custos->sum(fn($custo) => (float) ($custo->valor ?? 0));
    }

    /**
     * Calcula receita de um processo
     */
    public function calcularReceitaProcesso($processo): float
    {
        // Se tem contratos, usar soma dos contratos
        if (method_exists($processo, 'contratos') && $processo->contratos->count() > 0) {
            return $processo->contratos->sum('valor_total');
        }

        // Caso contrário, usar soma dos itens
        if (method_exists($processo, 'itens')) {
            return $processo->itens->sum(function($item) {
                return $item->valor_arrematado 
                    ?? $item->valor_negociado 
                    ?? $item->valor_final_sessao 
                    ?? 0;
            });
        }

        return 0;
    }

    /**
     * Calcula saldo a receber de um processo
     */
    public function calcularSaldoReceber($processo): float
    {
        // Se tem contratos, usar saldo dos contratos
        if (method_exists($processo, 'contratos') && $processo->contratos->count() > 0) {
            return $processo->contratos->sum('saldo');
        }

        // Caso contrário, receita = saldo a receber
        return $this->calcularReceitaProcesso($processo);
    }

    /**
     * Calcula custos diretos de um processo (notas fiscais de entrada)
     */
    public function calcularCustosDiretosProcesso($processo): float
    {
        if (!method_exists($processo, 'notasFiscais')) {
            return 0;
        }

        return $processo->notasFiscais
            ->where('tipo', 'entrada')
            ->sum('valor');
    }
}

