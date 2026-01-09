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

            // Buscar plano para obter nome
            $plano = \App\Modules\Assinatura\Models\Plano::find($novoPlanoId);
            if (!$plano) {
                Log::warning('AtualizarComissaoIndicacaoUseCase - Plano não encontrado', [
                    'plano_id' => $novoPlanoId,
                ]);
                return $indicacao;
            }

            // Calcular nova comissão baseada no novo valor pago
            $comissaoPercentual = $indicacao->comissao_percentual ?? 0;
            $novoValorComissao = ($novoValorPago * $comissaoPercentual) / 100;

            Log::info('AtualizarComissaoIndicacaoUseCase - Atualizando comissão', [
                'indicacao_id' => $indicacao->id,
                'plano_antigo_id' => $indicacao->plano_id,
                'plano_novo_id' => $novoPlanoId,
                'valor_antigo' => $indicacao->valor_plano_com_desconto,
                'valor_novo' => $novoValorPago,
                'comissao_antiga' => $indicacao->valor_comissao,
                'comissao_nova' => $novoValorComissao,
            ]);

            // Atualizar indicação
            $indicacao->update([
                'plano_id' => $novoPlanoId,
                'plano_nome' => $plano->nome,
                'valor_plano_original' => $plano->preco_mensal ?? 0,
                'valor_plano_com_desconto' => $novoValorPago,
                'valor_comissao' => $novoValorComissao,
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

