<?php

namespace App\Application\Relatorio\UseCases;

use App\Domain\Relatorio\Queries\RelatorioFinanceiroQuery;
use App\Modules\Relatorio\Services\FinanceiroService;
use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para gerar relatório financeiro
 * 
 * Responsabilidades:
 * - Agregar dados financeiros de processos
 * - Calcular receitas, custos, lucros e margens
 * - Gerenciar cache
 * - Retornar estrutura padronizada
 */
class GerarRelatorioFinanceiroUseCase
{
    private const CACHE_TTL = 3600; // 1 hora

    public function __construct(
        private RelatorioFinanceiroQuery $query,
        private FinanceiroService $financeiroService,
    ) {}

    /**
     * Gera relatório financeiro de processos em execução
     */
    public function executarExecucao(
        int $empresaId,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        $tenantId = null
    ): array {
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = $this->getCacheKeyExecucao($tenantId, $empresaId, $dataInicio, $dataFim);
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                Log::debug('RelatorioFinanceiro: dados obtidos do cache (execução)', [
                    'empresa_id' => $empresaId,
                ]);
                return $cached;
            }
        }

        // Buscar processos em execução
        $processos = $this->query->buscarProcessosExecucao($empresaId, $dataInicio, $dataFim);

        // Carregar relacionamentos necessários
        $processos->load(['itens', 'contratos', 'empenhos', 'notasFiscais']);

        // Calcular totais
        $totalReceber = 0;
        $totalCustosDiretos = 0;
        $totalSaldoReceber = 0;

        foreach ($processos as $processo) {
            $receita = $this->query->calcularReceitaProcesso($processo);
            $saldoReceber = $this->query->calcularSaldoReceber($processo);
            $custosDiretos = $this->query->calcularCustosDiretosProcesso($processo);

            $totalReceber += $receita;
            $totalSaldoReceber += $saldoReceber;
            $totalCustosDiretos += $custosDiretos;
        }

        // Calcular custos indiretos
        $totalCustosIndiretos = $this->query->calcularCustosIndiretos($empresaId, $dataInicio, $dataFim);

        // Calcular lucros e margens
        $lucroBruto = $totalReceber - $totalCustosDiretos;
        $lucroLiquido = $lucroBruto - $totalCustosIndiretos;
        $margemBruta = $totalReceber > 0 ? ($lucroBruto / $totalReceber) * 100 : 0;
        $margemLiquida = $totalReceber > 0 ? ($lucroLiquido / $totalReceber) * 100 : 0;

        // Mapear processos para resposta
        $processosMapeados = $processos->map(function($processo) {
            $receita = $this->query->calcularReceitaProcesso($processo);
            $saldoReceber = $this->query->calcularSaldoReceber($processo);
            $custosDiretos = $this->query->calcularCustosDiretosProcesso($processo);
            $lucro = $receita - $custosDiretos;
            $margem = $receita > 0 ? ($lucro / $receita) * 100 : 0;

            return [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade ?? $processo->numeroModalidade,
                'objeto_resumido' => $processo->objeto_resumido ?? $processo->objetoResumido,
                'receita' => $receita,
                'saldo_receber' => $saldoReceber,
                'custos_diretos' => $custosDiretos,
                'lucro' => $lucro,
                'margem' => round($margem, 2),
            ];
        });

        $resultado = [
            'resumo' => [
                'total_receber' => $totalReceber,
                'total_custos_diretos' => $totalCustosDiretos,
                'total_custos_indiretos' => $totalCustosIndiretos,
                'total_saldo_receber' => $totalSaldoReceber,
                'lucro_bruto' => $lucroBruto,
                'lucro_liquido' => $lucroLiquido,
                'margem_bruta' => round($margemBruta, 2),
                'margem_liquida' => round($margemLiquida, 2),
            ],
            'processos' => $processosMapeados->values()->all(),
        ];

        // Salvar no cache
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = $this->getCacheKeyExecucao($tenantId, $empresaId, $dataInicio, $dataFim);
            RedisService::set($cacheKey, $resultado, self::CACHE_TTL);
        }

        return $resultado;
    }

    /**
     * Gera relatório financeiro mensal
     */
    public function executarMensal(
        int $empresaId,
        Carbon $mes,
        $tenantId = null
    ): array {
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::getRelatorioFinanceiro(
                $tenantId,
                $mes->month,
                $mes->year
            );
            if ($cached !== null) {
                Log::debug('RelatorioFinanceiro: dados obtidos do cache (mensal)', [
                    'empresa_id' => $empresaId,
                    'mes' => $mes->format('Y-m'),
                ]);
                return $cached;
            }
        }

        // Calcular gestão financeira mensal
        $resultado = $this->financeiroService->calcularGestaoFinanceiraMensal($mes, $empresaId);

        // Salvar no cache
        if ($tenantId && RedisService::isAvailable()) {
            RedisService::cacheRelatorioFinanceiro(
                $tenantId,
                $mes->month,
                $mes->year,
                $resultado,
                self::CACHE_TTL
            );
        }

        return $resultado;
    }

    /**
     * Gera chave de cache para relatório de execução
     */
    private function getCacheKeyExecucao($tenantId, int $empresaId, ?string $dataInicio, ?string $dataFim): string
    {
        $periodo = md5(($dataInicio ?? '') . ($dataFim ?? ''));
        return "relatorio_financeiro_execucao_{$tenantId}_{$empresaId}_{$periodo}";
    }
}

