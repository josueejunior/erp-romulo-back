<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Modules\Assinatura\Models\Assinatura as AssinaturaModel;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Implementação do Repository de Assinatura usando Eloquent
 * Esta é a única camada que conhece Eloquent/banco de dados
 */
class AssinaturaRepository implements AssinaturaRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(AssinaturaModel $model): Assinatura
    {
        return new Assinatura(
            id: $model->id,
            userId: $model->user_id, // NOVO: userId é obrigatório
            tenantId: $model->tenant_id, // Mantido para compatibilidade
            planoId: $model->plano_id,
            status: $model->status,
            dataInicio: $model->data_inicio ? Carbon::parse($model->data_inicio) : null,
            dataFim: $model->data_fim ? Carbon::parse($model->data_fim) : null,
            dataCancelamento: $model->data_cancelamento ? Carbon::parse($model->data_cancelamento) : null,
            valorPago: $model->valor_pago ? (float) $model->valor_pago : null,
            metodoPagamento: $model->metodo_pagamento,
            transacaoId: $model->transacao_id,
            diasGracePeriod: $model->dias_grace_period ?? 7,
            observacoes: $model->observacoes,
        );
    }

    /**
     * Buscar assinatura por ID
     */
    public function buscarPorId(int $id): ?Assinatura
    {
        $model = AssinaturaModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Buscar assinatura atual do tenant
     */
public function buscarAssinaturaAtual(int $tenantId): ?Assinatura
    {
        // Buscar tenant para verificar assinatura_atual_id
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            \Log::warning('AssinaturaRepository::buscarAssinaturaAtual() - Tenant não encontrado', [
                'tenant_id' => $tenantId,
            ]);
            return null;
        }

        $assinatura = null;

        // 🔥 IMPORTANTE: Sempre garantir que o tenant correto está inicializado
        // Se já estiver inicializado com outro tenant, reinicializar
        $jaInicializado = tenancy()->initialized;
        $tenantAtual = tenancy()->tenant;
        $precisaReinicializar = !$jaInicializado || ($tenantAtual && $tenantAtual->id !== $tenantId);
        
        \Log::debug('AssinaturaRepository::buscarAssinaturaAtual() - Verificando tenant', [
            'tenant_id_solicitado' => $tenantId,
            'tenant_id_atual' => $tenantAtual?->id,
            'ja_inicializado' => $jaInicializado,
            'precisa_reinicializar' => $precisaReinicializar,
        ]);
        
        try {
            if ($precisaReinicializar) {
                if ($jaInicializado) {
                    tenancy()->end();
                }
                tenancy()->initialize($tenant);
                \Log::debug('AssinaturaRepository::buscarAssinaturaAtual() - Tenant reinicializado', [
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Se o tenant tem assinatura_atual_id, buscar por ele
            if ($tenant->assinatura_atual_id) {
                \Log::debug('AssinaturaRepository::buscarAssinaturaAtual() - Buscando por assinatura_atual_id', [
                    'tenant_id' => $tenantId,
                    'assinatura_atual_id' => $tenant->assinatura_atual_id,
                ]);
                
                $model = AssinaturaModel::with('plano')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $tenant->assinatura_atual_id)
                    ->first();
                
                if ($model) {
                    \Log::info('AssinaturaRepository::buscarAssinaturaAtual() - Assinatura encontrada por assinatura_atual_id', [
                        'tenant_id' => $tenantId,
                        'assinatura_id' => $model->id,
                        'status' => $model->status,
                    ]);
                    $assinatura = $this->toDomain($model);
                } else {
                    \Log::warning('AssinaturaRepository::buscarAssinaturaAtual() - Assinatura não encontrada por assinatura_atual_id', [
                        'tenant_id' => $tenantId,
                        'assinatura_atual_id' => $tenant->assinatura_atual_id,
                    ]);
                }
            }

            // Se não encontrou, buscar a assinatura mais recente do tenant
            if (!$assinatura) {
                \Log::debug('AssinaturaRepository::buscarAssinaturaAtual() - Buscando assinatura mais recente', [
                    'tenant_id' => $tenantId,
                ]);
                
                // Contar total de assinaturas para debug
                $totalAssinaturas = AssinaturaModel::where('tenant_id', $tenantId)->count();
                $totalNaoCanceladas = AssinaturaModel::where('tenant_id', $tenantId)
                    ->where('status', '!=', 'cancelada')
                    ->count();
                
                \Log::debug('AssinaturaRepository::buscarAssinaturaAtual() - Estatísticas de assinaturas', [
                    'tenant_id' => $tenantId,
                    'total_assinaturas' => $totalAssinaturas,
                    'total_nao_canceladas' => $totalNaoCanceladas,
                ]);
                
                $model = AssinaturaModel::with('plano')
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'cancelada')
                    ->orderBy('data_fim', 'desc')
                    ->orderBy('criado_em', 'desc')
                    ->first();
                
                if ($model) {
                    \Log::info('AssinaturaRepository::buscarAssinaturaAtual() - Assinatura encontrada (mais recente)', [
                        'tenant_id' => $tenantId,
                        'assinatura_id' => $model->id,
                        'status' => $model->status,
                        'data_fim' => $model->data_fim?->format('Y-m-d'),
                    ]);
                    
                    $assinatura = $this->toDomain($model);
                    
                    // Se encontrou e o tenant não tinha assinatura_atual_id, atualizar
                    if (!$tenant->assinatura_atual_id) {
                        $tenant->update([
                            'assinatura_atual_id' => $model->id,
                            'plano_atual_id' => $model->plano_id,
                        ]);
                        
                        \Log::info('AssinaturaRepository::buscarAssinaturaAtual() - Tenant atualizado com assinatura_atual_id', [
                            'tenant_id' => $tenantId,
                            'assinatura_atual_id' => $model->id,
                        ]);
                    }
                } else {
                    \Log::warning('AssinaturaRepository::buscarAssinaturaAtual() - Nenhuma assinatura encontrada', [
                        'tenant_id' => $tenantId,
                        'total_assinaturas' => $totalAssinaturas,
                        'total_nao_canceladas' => $totalNaoCanceladas,
                    ]);
                }
            }
        } finally {
            // Sempre finalizar o contexto se foi inicializado aqui
            if (!$jaInicializado && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $assinatura;
    }

    /**
     * Listar assinaturas do tenant
     */
    public function listarPorTenant(int $tenantId, array $filtros = []): Collection
    {
        $query = AssinaturaModel::where('tenant_id', $tenantId);

        // Aplicar filtros
        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        $models = $query->orderBy('criado_em', 'desc')->get();

        return $models->map(fn($model) => $this->toDomain($model));
    }

    /**
     * Buscar modelo Eloquent por ID
     */
    public function buscarModeloPorId(int $id): ?AssinaturaModel
    {
        return AssinaturaModel::with('plano')->find($id);
    }

    /**
     * Buscar modelo Eloquent por transacao_id
     */
    public function buscarModeloPorTransacaoId(string $transacaoId): ?AssinaturaModel
    {
        return AssinaturaModel::with('plano', 'tenant')
            ->where('transacao_id', $transacaoId)
            ->first();
    }

    /**
     * Salvar assinatura (criar ou atualizar)
     * 
     * 🔥 CRÍTICO: Garante que o tenancy está inicializado para o tenant correto
     * antes de salvar, para que a assinatura seja criada no banco do tenant.
     */
    public function salvar(Assinatura $assinatura): Assinatura
    {
        // Buscar tenant
        $tenant = Tenant::find($assinatura->tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant não encontrado: {$assinatura->tenantId}");
        }

        // 🔥 CRÍTICO: Garantir que o tenancy está inicializado para o tenant correto
        $jaInicializado = tenancy()->initialized;
        $tenantAtual = tenancy()->tenant;
        $precisaReinicializar = !$jaInicializado || ($tenantAtual && $tenantAtual->id !== $assinatura->tenantId);
        
        \Log::debug('AssinaturaRepository::salvar() - Verificando tenancy', [
            'tenant_id' => $assinatura->tenantId,
            'tenant_id_atual' => $tenantAtual?->id,
            'ja_inicializado' => $jaInicializado,
            'precisa_reinicializar' => $precisaReinicializar,
            'assinatura_id' => $assinatura->id,
        ]);
        
        try {
            if ($precisaReinicializar) {
                if ($jaInicializado) {
                    tenancy()->end();
                }
                tenancy()->initialize($tenant);
                \Log::debug('AssinaturaRepository::salvar() - Tenancy inicializado', [
                    'tenant_id' => $tenant->id,
                ]);
            }

            if ($assinatura->id) {
                // Atualizar
                $model = AssinaturaModel::findOrFail($assinatura->id);
                $model->update([
                    'tenant_id' => $assinatura->tenantId,
                    'plano_id' => $assinatura->planoId,
                    'status' => $assinatura->status,
                    'data_inicio' => $assinatura->dataInicio,
                    'data_fim' => $assinatura->dataFim,
                    'data_cancelamento' => $assinatura->dataCancelamento,
                    'valor_pago' => $assinatura->valorPago,
                    'metodo_pagamento' => $assinatura->metodoPagamento,
                    'transacao_id' => $assinatura->transacaoId,
                    'dias_grace_period' => $assinatura->diasGracePeriod,
                    'observacoes' => $assinatura->observacoes,
                ]);
                
                \Log::info('AssinaturaRepository::salvar() - Assinatura atualizada', [
                    'tenant_id' => $assinatura->tenantId,
                    'assinatura_id' => $model->id,
                    'status' => $model->status,
                ]);
            } else {
                // Criar
                $model = AssinaturaModel::create([
                    'tenant_id' => $assinatura->tenantId,
                    'plano_id' => $assinatura->planoId,
                    'status' => $assinatura->status,
                    'data_inicio' => $assinatura->dataInicio ?? now(),
                    'data_fim' => $assinatura->dataFim,
                    'data_cancelamento' => $assinatura->dataCancelamento,
                    'valor_pago' => $assinatura->valorPago ?? 0,
                    'metodo_pagamento' => $assinatura->metodoPagamento ?? 'gratuito',
                    'transacao_id' => $assinatura->transacaoId,
                    'dias_grace_period' => $assinatura->diasGracePeriod ?? 7,
                    'observacoes' => $assinatura->observacoes,
                ]);
                
                \Log::info('AssinaturaRepository::salvar() - Assinatura criada', [
                    'tenant_id' => $assinatura->tenantId,
                    'assinatura_id' => $model->id,
                    'status' => $model->status,
                    'data_fim' => $model->data_fim?->format('Y-m-d'),
                ]);
            }

            return $this->toDomain($model->fresh());
        } finally {
            // Sempre finalizar o contexto se foi inicializado aqui
            if (!$jaInicializado && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}

