<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Application\Afiliado\UseCases\AtualizarComissaoIndicacaoUseCase;
use App\Modules\Assinatura\Models\Assinatura;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para Upgrade/Downgrade de Planos
 * 
 * Calcula pro-rata (valor proporcional) ao trocar de plano
 * 
 * 🔥 ARQUITETURA LIMPA: Usa TenantRepository em vez de Eloquent direto
 */

/**
 * Use Case para Upgrade/Downgrade de Planos
 * 
 * Calcula pro-rata (valor proporcional) ao trocar de plano
 */
class TrocarPlanoAssinaturaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AtualizarComissaoIndicacaoUseCase $atualizarComissaoIndicacaoUseCase,
    ) {}

    /**
     * Calcular valores da troca de plano sem executar
     */
    /**
     * Calcular valores da troca de plano sem executar
     */
    public function simular(int $tenantId, int $novoPlanoId, string $periodo = 'mensal'): array
    {
        // Buscar assinatura atual (fonte da verdade do que o usuário tem ATIVO)
        // 🔥 CORREÇÃO: Usar Query Object para priorizar status ativa/trial
        $assinaturaAtual = \App\Domain\Assinatura\Queries\AssinaturaQueries::assinaturaAtualPorTenant($tenantId);

        Log::debug('TrocarPlanoAssinaturaUseCase::simular - Buscando assinatura', [
            'tenant_id' => $tenantId,
            'encontrada' => $assinaturaAtual ? $assinaturaAtual->id : 'não',
            'status' => $assinaturaAtual ? $assinaturaAtual->status : null,
        ]);

        if (!$assinaturaAtual) {
            throw new DomainException('Nenhuma assinatura ativa encontrada para simular troca de plano');
        }

        // Buscar novo plano
        $novoPlano = $this->planoRepository->buscarModeloPorId($novoPlanoId);
        if (!$novoPlano || !$novoPlano->isAtivo()) {
            throw new DomainException('Plano não encontrado ou inativo');
        }

        return $this->calcularValores($assinaturaAtual, $novoPlano, $periodo);
    }

    /**
     * Executar troca de plano
     */
    public function executar(int $tenantId, int $novoPlanoId, string $periodo = 'mensal'): array
    {
        return DB::transaction(function () use ($tenantId, $novoPlanoId, $periodo) {
            // Buscar assinatura atual (fonte da verdade do que o usuário tem ATIVO)
            // 🔥 CORREÇÃO: Usar Query Object para priorizar status ativa/trial
            $assinaturaAtual = \App\Domain\Assinatura\Queries\AssinaturaQueries::assinaturaAtualPorTenant($tenantId);

            Log::info('TrocarPlanoAssinaturaUseCase::executar - Iniciando troca', [
                'tenant_id' => $tenantId,
                'assinatura_atual_id' => $assinaturaAtual ? $assinaturaAtual->id : null,
                'novo_plano_id' => $novoPlanoId,
            ]);

            if (!$assinaturaAtual) {
                throw new DomainException('Nenhuma assinatura ativa encontrada para trocar de plano');
            }

            // Buscar novo plano
            $novoPlano = $this->planoRepository->buscarModeloPorId($novoPlanoId);
            if (!$novoPlano || !$novoPlano->isAtivo()) {
                throw new DomainException('Plano não encontrado ou inativo');
            }

            // Não permitir trocar para o mesmo plano se a assinatura atual já for ATIVA para esse plano
            if ((int)$assinaturaAtual->plano_id === (int)$novoPlanoId && $assinaturaAtual->status === 'ativa') {
                // Verificar se é apenas troca de período (mensal/anual)
                $dataFim = $assinaturaAtual->data_fim;
                $dataInicio = $assinaturaAtual->data_inicio;
                $diffMeses = $dataInicio->diffInMonths($dataFim);
                
                $periodoAtual = $diffMeses >= 11 ? 'anual' : 'mensal';
                
                if ($periodoAtual === $periodo) {
                    throw new DomainException('Você já está neste plano');
                }
            }

            // 🔥 MELHORIA: Cancelar outras assinaturas 'aguardando_pagamento' para o mesmo tenant
            // Isso evita lixo no banco e confusão de múltiplas cobranças pendentes
            Assinatura::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'aguardando_pagamento')
                ->where('plano_id', $novoPlanoId)
                ->update([
                    'status' => 'cancelada',
                    'observacoes' => DB::raw("COALESCE(observacoes, '') || '\nCancelada por nova tentativa de troca de plano em " . now()->format('Y-m-d H:i:s') . "'")
                ]);

            $valores = $this->calcularValores($assinaturaAtual, $novoPlano, $periodo);
            $creditoProporcional = $valores['credito'];
            $valorCobrar = $valores['valor_cobrar'];
            $valorNovoPlano = $valores['novo_valor'];

            // Cancelar assinatura atual APENAS se não houver cobrança (downgrade ou crédito suficiente)
            // Se houver cobrança (aguardando_pagamento), a anterior continua ativa até o pagamento confirmar
            if ($valorCobrar <= 0) {
                // 🔥 CORREÇÃO: Cancelar TODAS as assinaturas ativas/trial do tenant para evitar colisão de unique constraint
                // "assinaturas_tenant_ativa_unique" impede duplicidade de status 'ativa'
                $assinaturasParaCancelar = Assinatura::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['cancelada', 'expirada'])
                    ->get();

                foreach ($assinaturasParaCancelar as $assinaturaParaCancelar) {
                    $assinaturaParaCancelar->update([
                        'status' => 'cancelada',
                        'data_cancelamento' => now(),
                        'observacoes' => ($assinaturaParaCancelar->observacoes ?? '') . "\n\nCancelada para upgrade/downgrade de plano em " . now()->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Criar nova assinatura
            $dataInicio = now();
            $dataFim = $periodo === 'anual' 
                ? $dataInicio->copy()->addYear()
                : $dataInicio->copy()->addMonth();

            // 🔥 NOVO: Tentar obter empresa_id do contexto ou da assinatura anterior
            $empresaId = $assinaturaAtual->empresa_id;
            if (!$empresaId) {
                $empresaId = request()->attributes->get('empresa_id') ?? app('current_empresa_id') ?? null;
            }

            $novaAssinatura = Assinatura::create([
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId, // 🔥 GARANTIA: Novo registro terá empresa_id
                'plano_id' => $novoPlanoId,
                'status' => $valorCobrar > 0 ? 'aguardando_pagamento' : 'ativa', // 🔥 CORRIGIDO: 'aguardando_pagamento' ao invés de 'suspensa'
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'valor_pago' => $valorNovoPlano,
                'metodo_pagamento' => null,
                'transacao_id' => null,
                'dias_grace_period' => 7,
                'observacoes' => "Criada por upgrade/downgrade. Crédito aplicado: R$ {$creditoProporcional}. Valor cobrado: R$ {$valorCobrar}",
            ]);

            // Atualizar tenant usando repository APENAS se a assinatura estiver ativa
            // Se estiver aguardando pagamento, o webhook ou callback fará a atualização
            $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantId);
            if ($tenantModel && $novaAssinatura->status === 'ativa') {
                $tenantModel->update([
                    'plano_atual_id' => $novoPlanoId,
                    'assinatura_atual_id' => $novaAssinatura->id,
                ]);
            }

            return [
                'assinatura' => $novaAssinatura,
                'assinatura_antiga' => $assinaturaAtual,
                'credito' => $creditoProporcional,
                'valor_cobrar' => $valorCobrar,
                'status' => $valorCobrar > 0 ? 'aguardando_pagamento' : 'ativado',
            ];
        });
    }

    /**
     * Calcula os valores de crédito e cobrança
     */
    private function calcularValores(Assinatura $assinaturaAtual, $novoPlano, string $periodo): array
    {
        $planoAtual = $assinaturaAtual->plano;
        // Calcular valor do novo plano com desconto promocional de 50% (sincronizado com frontend)
        if ($periodo === 'anual') {
            $valorNovoPlano = $novoPlano->preco_anual ?: ($novoPlano->preco_mensal * 10);
        } else {
            $valorNovoPlano = $novoPlano->preco_mensal;
        }
        
        // 🔥 CRÍTICO: Se valor_pago não existe ou é 0, usar valor do plano atual
        $valorPlanoAtual = $assinaturaAtual->valor_pago;
        if (!$valorPlanoAtual || $valorPlanoAtual == 0) {
            $valorPlanoAtual = $planoAtual->preco_mensal ?? 0;
            Log::info('TrocarPlanoAssinaturaUseCase - Usando valor do plano atual (valor_pago estava vazio)', [
                'plano_atual_id' => $planoAtual->id,
                'valor_plano_atual' => $valorPlanoAtual,
            ]);
        }

        // Calcular dias restantes da assinatura atual
        $diasRestantes = now()->diffInDays($assinaturaAtual->data_fim, false);
        if ($diasRestantes <= 0) {
            $diasRestantes = 0;
        }

        // Calcular total de dias do período atual
        $diasTotais = $assinaturaAtual->data_inicio->diffInDays($assinaturaAtual->data_fim);
        if ($diasTotais <= 0) {
            $diasTotais = 30; // fallback
        }

        // Calcular crédito proporcional (pro-rata)
        $creditoProporcional = ($valorPlanoAtual / $diasTotais) * $diasRestantes;
        $creditoProporcional = round($creditoProporcional, 2);

        // Calcular valor a cobrar
        $valorCobrar = max(0, $valorNovoPlano - $creditoProporcional);
        $valorCobrar = round($valorCobrar, 2);

        Log::info('Calculando troca de plano', [
            'plano_atual' => $planoAtual->nome,
            'novo_plano' => $novoPlano->nome,
            'dias_restantes' => $diasRestantes,
            'dias_totais' => $diasTotais,
            'valor_plano_atual' => $valorPlanoAtual,
            'valor_novo_plano' => $valorNovoPlano,
            'credito_proporcional' => $creditoProporcional,
            'valor_cobrar' => $valorCobrar,
        ]);

        return [
            'credito' => $creditoProporcional,
            'valor_cobrar' => $valorCobrar,
            'novo_valor' => $valorNovoPlano,
        ];
    }
}
