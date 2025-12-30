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
            tenantId: $model->tenant_id,
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
            return null;
        }

        $assinatura = null;

        // Se o tenant tem assinatura_atual_id, buscar por ele
        if ($tenant->assinatura_atual_id) {
            $model = AssinaturaModel::with('plano')
                ->where('tenant_id', $tenantId)
                ->where('id', $tenant->assinatura_atual_id)
                ->first();
            
            if ($model) {
                $assinatura = $this->toDomain($model);
            }
        }

        // Se não encontrou, buscar a assinatura mais recente do tenant
        if (!$assinatura) {
            $model = AssinaturaModel::with('plano')
                ->where('tenant_id', $tenantId)
                ->orderBy('data_fim', 'desc')
                ->orderBy('criado_em', 'desc')
                ->first();
            
            if ($model) {
                $assinatura = $this->toDomain($model);
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
}

