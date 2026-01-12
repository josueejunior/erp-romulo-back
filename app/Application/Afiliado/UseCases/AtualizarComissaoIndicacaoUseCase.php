<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\AfiliadoIndicacao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Atualizar Comissão de Indicação
 * 
 * Atualiza valores de comissão quando cliente faz upgrade/downgrade
 */
final class AtualizarComissaoIndicacaoUseCase
{
    /**
     * Atualiza comissão de indicação após troca de plano
     * 
     * @param int $tenantId ID do tenant
     * @param int $empresaId ID da empresa
     * @param int $novoPlanoId ID do novo plano
     * @param float $novoValorPago Novo valor pago
     * @return AfiliadoIndicacao|null
     */
    public function executar(
        int $tenantId,
        int $empresaId,
        int $novoPlanoId,
        float $novoValorPago
    ): ?AfiliadoIndicacao {
        return DB::transaction(function () use ($tenantId, $empresaId, $novoPlanoId, $novoValorPago) {
            // Buscar indicação
            $indicacao = AfiliadoIndicacao::where('tenant_id', $tenantId)
                ->where('empresa_id', $empresaId)
                ->where('status', 'ativa')
                ->first();

            if (!$indicacao) {
                Log::debug('AtualizarComissaoIndicacaoUseCase - Nenhuma indicação encontrada', [
                    'tenant_id' => $tenantId,
                    'empresa_id' => $empresaId,
                ]);
                return null;
            }

            // Buscar plano para obter nome e percentual de comissão
            $plano = \App\Modules\Assinatura\Models\Plano::find($novoPlanoId);
            if (!$plano) {
                Log::warning('AtualizarComissaoIndicacaoUseCase - Plano não encontrado', [
                    'plano_id' => $novoPlanoId,
                ]);
                return $indicacao;
            }

            // 🔥 NOVA LÓGICA DE CÁLCULO DE COMISSÃO:
            // Recalcular comissão usando nova fórmula baseada no novo plano
            // Base fixa: 30%
            // Percentual do plano (peso): vem do campo percentual_comissao_afiliado (40%, 60%, 100%)
            // Comissão real = (30% × percentual_do_plano) / 100
            // Valor da comissão = (valor_do_plano × comissão_real) / 100
            $baseComissao = 30.0; // Base fixa de 30%
            $percentualPlano = (float) ($plano->percentual_comissao_afiliado ?? 100.0); // Padrão: 100% (Premium)
            $novaComissaoReal = ($baseComissao * $percentualPlano) / 100; // Comissão real: 12%, 18% ou 30%
            $novoValorComissao = ($novoValorPago * $novaComissaoReal) / 100; // Valor final da comissão

            Log::info('AtualizarComissaoIndicacaoUseCase - Atualizando comissão com nova lógica', [
                'indicacao_id' => $indicacao->id,
                'plano_antigo_id' => $indicacao->plano_id,
                'plano_novo_id' => $novoPlanoId,
                'plano_nome' => $plano->nome,
                'valor_antigo' => $indicacao->valor_plano_com_desconto,
                'valor_novo' => $novoValorPago,
                'base_comissao' => $baseComissao, // 30%
                'percentual_plano_antigo' => $indicacao->comissao_percentual ?? 0,
                'percentual_plano_novo' => $percentualPlano, // 40%, 60% ou 100%
                'comissao_real_antiga' => $indicacao->comissao_percentual ?? 0,
                'comissao_real_nova' => round($novaComissaoReal, 2), // 12%, 18% ou 30%
                'comissao_antiga' => $indicacao->valor_comissao,
                'comissao_nova' => round($novoValorComissao, 2),
            ]);

            // Atualizar indicação
            $indicacao->update([
                'plano_id' => $novoPlanoId,
                'plano_nome' => $plano->nome,
                'valor_plano_original' => $plano->preco_mensal ?? 0,
                'valor_plano_com_desconto' => $novoValorPago,
                'comissao_percentual' => round($novaComissaoReal, 2), // Atualizar comissão REAL calculada
                'valor_comissao' => round($novoValorComissao, 2),
            ]);

            return $indicacao->fresh();
        });
    }

    /**
     * Atualiza status da indicação quando assinatura é cancelada/expirada
     */
    public function atualizarStatus(int $tenantId, int $empresaId, string $status): ?AfiliadoIndicacao
    {
        return DB::transaction(function () use ($tenantId, $empresaId, $status) {
            $indicacao = AfiliadoIndicacao::where('tenant_id', $tenantId)
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$indicacao) {
                return null;
            }

            $indicacao->update([
                'status' => $status,
                'cancelado_em' => in_array($status, ['cancelada', 'expirada']) ? now() : null,
            ]);

            Log::info('AtualizarComissaoIndicacaoUseCase - Status atualizado', [
                'indicacao_id' => $indicacao->id,
                'status' => $status,
            ]);

            return $indicacao->fresh();
        });
    }
}



