<?php

namespace App\Domain\Processo\Services;

use App\Domain\Processo\Entities\Processo;

/**
 * Domain Service para cálculos de saldo e financeiros do processo
 * 
 * Contém a lógica de negócio pura para cálculos de processos arrematados,
 * saldos vinculados e empenhos, independente da infraestrutura.
 */
class CalculoSaldoService
{
    /**
     * Calcula o potencial financeiro (arrematado) de um processo baseado em seus itens
     */
    public function calcularPotencialFinanceiro(array $itens): float
    {
        $total = 0;
        foreach ($itens as $item) {
            // Regra: Itens aceitos ou em execução representam potencial de receita
            if (in_array($item['status_item'], ['aceito', 'aceito_habilitado', 'aguardando_entrega', 'execucao'])) {
                // Prioridade de valor: Negociado -> Final Sessão -> Estimado
                $valorUnitario = $item['valor_negociado'] > 0 
                    ? $item['valor_negociado'] 
                    : ($item['valor_final_sessao'] > 0 ? $item['valor_final_sessao'] : $item['valor_estimado']);
                
                $total += $valorUnitario * $item['quantidade'];
            }
        }
        return round($total, 2);
    }

    /**
     * Calcula a margem de lucro bruta
     */
    /**
     * Calcula o saldo total vinculado (Contratos + AFs)
     * 
     * @param array $contratos Array de dados dos contratos
     * @param array $afs Array de dados das AFs
     * @return float
     */
    public function calcularSaldoVinculado(array $contratos, array $afs): float
    {
        $totalContratos = array_reduce($contratos, fn($carry, $c) => $carry + ($c['valor_total'] ?? 0), 0.0);
        
        // AFs vinculadas a contratos não devem ser somadas novamente
        $totalAfsSemContrato = array_reduce($afs, function($carry, $af) {
            if (empty($af['contrato_id'])) {
                return $carry + ($af['valor'] ?? 0);
            }
            return $carry;
        }, 0.0);

        return round($totalContratos + $totalAfsSemContrato, 2);
    }

    /**
     * Calcula o saldo total empenhado
     * 
     * @param array $empenhos Array de dados dos empenhos
     * @return float
     */
    public function calcularSaldoEmpenhado(array $empenhos): float
    {
        $total = array_reduce($empenhos, fn($carry, $e) => $carry + ($e['valor'] ?? 0), 0.0);
        return round($total, 2);
    }
}
