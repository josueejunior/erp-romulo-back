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
    public function simular(int $tenantId, int $novoPlanoId, string $periodo = 'mensal'): array
    {
        // Buscar assinatura atual
        // 🔥 DDD/LEGADO: Usamos withoutGlobalScopes() para garantir que encontramos a assinatura mesmo se houver filtros de empresa ou tenancy
        // e permitimos qualquer status que não esteja encerrado (cancelado/expirado)
        $assinaturaAtual = Assinatura::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelada', 'expirada'])
            ->orderByDesc('id') // Pegar a mais recente se houver mais de uma
            ->first();

        Log::debug('TrocarPlanoAssinaturaUseCase::simular - Buscando assinatura', [
            'tenant_id' => $tenantId,
            'encontrada' => $assinaturaAtual ? $assinaturaAtual->id : 'não',
            'status' => $assinaturaAtual ? $assinaturaAtual->status : null,
            'empresa_id' => $assinaturaAtual ? $assinaturaAtual->empresa_id : null,
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
     * 
     * @param int $tenantId
     * @param int $novoPlanoId
     * @param string $periodo 'mensal' ou 'anual'
     * @return array ['assinatura' => Assinatura, 'credito' => float, 'valor_cobrar' => float]
     */
    public function executar(int $tenantId, int $novoPlanoId, string $periodo = 'mensal'): array
    {
        return DB::transaction(function () use ($tenantId, $novoPlanoId, $periodo) {
            // Buscar assinatura atual
            // 🔥 DDD/LEGADO: Usamos withoutGlobalScopes() para garantir que encontramos a assinatura
            $assinaturaAtual = Assinatura::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelada', 'expirada'])
                ->orderByDesc('id')
                ->first();

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

            // Não permitir trocar para o mesmo plano
            if ($assinaturaAtual->plano_id === $novoPlanoId) {
                throw new DomainException('Você já está neste plano');
            }

            $valores = $this->calcularValores($assinaturaAtual, $novoPlano, $periodo);
            $creditoProporcional = $valores['credito'];
            $valorCobrar = $valores['valor_cobrar'];
            $valorNovoPlano = $valores['novo_valor'];

            // Cancelar assinatura atual
            $assinaturaAtual->update([
                'status' => 'cancelada',
                'data_cancelamento' => now(),
                'observacoes' => ($assinaturaAtual->observacoes ?? '') . "\n\nCancelada para upgrade/downgrade de plano em " . now()->format('Y-m-d H:i:s'),
            ]);

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
                'status' => $valorCobrar > 0 ? 'suspensa' : 'ativa',
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'valor_pago' => $valorNovoPlano,
                'metodo_pagamento' => null,
                'transacao_id' => null,
                'dias_grace_period' => 7,
                'observacoes' => "Criada por upgrade/downgrade. Crédito aplicado: R$ {$creditoProporcional}. Valor cobrado: R$ {$valorCobrar}",
            ]);

            // Atualizar tenant usando repository
            $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantId);
            if ($tenantModel) {
                $tenantModel->update([
                    'plano_atual_id' => $novoPlanoId,
                    'assinatura_atual_id' => $novaAssinatura->id,
                ]);
            }

            return [
                'assinatura' => $novaAssinatura,
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
        $descontoPromocional = 0.5;
        $precosMensaisPromocionais = [
            'Essencial' => 138.57,
            'Profissional' => 171.43,
            'Master' => 228.57,
            'Ilimitado' => 427.14,
        ];

        if ($periodo === 'anual') {
            $precoBaseAnual = $novoPlano->preco_anual ?: ($novoPlano->preco_mensal * 10);
            $valorNovoPlano = $precoBaseAnual * $descontoPromocional;
        } else {
            $valorNovoPlano = $precosMensaisPromocionais[$novoPlano->nome] ?? ($novoPlano->preco_mensal * $descontoPromocional);
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
