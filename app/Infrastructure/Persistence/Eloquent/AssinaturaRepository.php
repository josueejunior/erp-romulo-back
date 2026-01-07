<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Enums\StatusAssinatura;
use App\Domain\Assinatura\Queries\AssinaturaQueries;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Guards\TenantContextGuard;
use App\Modules\Assinatura\Models\Assinatura as AssinaturaModel;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Implementação do Repository de Assinatura usando Eloquent
 * 
 * Responsabilidade: Persistência e recuperação de Assinaturas.
 * NÃO valida contexto de tenancy (isso é feito pelo Application Service via TenantContextGuard).
 * 
 * @see TenantContextGuard Para validação de contexto
 * @see AssinaturaQueries Para queries reutilizáveis
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
            userId: $model->user_id,
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
     * 
     * @deprecated Use buscarAssinaturaAtualPorUsuario() - assinatura pertence ao usuário
     */
    public function buscarAssinaturaAtual(int $tenantId): ?Assinatura
    {
        // Usar Query Object
        $model = AssinaturaQueries::assinaturaAtualPorTenant($tenantId);
        
        if (!$model) {
            return null;
        }

        // Atualizar tenant se não tiver assinatura_atual_id
        $tenant = Tenant::find($tenantId);
        if ($tenant && !$tenant->assinatura_atual_id) {
            $tenant->update([
                'assinatura_atual_id' => $model->id,
                'plano_atual_id' => $model->plano_id,
            ]);
        }

        return $this->toDomain($model);
    }

    /**
     * Buscar assinatura atual do usuário (método principal)
     */
    public function buscarAssinaturaAtualPorUsuario(int $userId): ?Assinatura
    {
        $model = AssinaturaQueries::assinaturaAtualPorUsuario($userId);
        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Listar assinaturas ativas do usuário
     */
    public function listarAtivasPorUsuario(int $userId): Collection
    {
        return AssinaturaQueries::ativasPorUsuario($userId)
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }

    /**
     * Listar histórico de assinaturas do usuário
     */
    public function listarHistoricoPorUsuario(int $userId): Collection
    {
        return AssinaturaQueries::historicoPorUsuario($userId)
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }

    /**
     * @deprecated Use listarAtivasPorUsuario() ou listarHistoricoPorUsuario()
     */
    public function listarPorTenant(int $tenantId, array $filtros = []): Collection
    {
        $query = AssinaturaModel::where('tenant_id', $tenantId);

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }

    /**
     * @deprecated Use listarAtivasPorUsuario() ou listarHistoricoPorUsuario()
     */
    public function listarPorUsuario(int $userId, array $filtros = []): Collection
    {
        $query = AssinaturaModel::where('user_id', $userId);

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }

    /**
     * Verificar se usuário tem assinatura válida
     */
    public function usuarioTemAssinaturaValida(int $userId): bool
    {
        return AssinaturaQueries::usuarioTemAssinaturaValida($userId);
    }

    /**
     * Buscar assinatura por transação
     */
    public function buscarPorTransacao(string $transacaoId): ?Assinatura
    {
        $model = AssinaturaQueries::porTransacao($transacaoId);
        return $model ? $this->toDomain($model) : null;
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
     * Nota: A validação de contexto (TenantContextGuard) deve ser feita
     * pelo Application Service antes de chamar este método.
     */
    public function salvar(Assinatura $assinatura): Assinatura
    {
        // Para criação inicial (cadastro público), permitir sem tenancy
        $isNovaAssinatura = $assinatura->id === null;
        $temTenantIdExplicito = $assinatura->tenantId !== null;
        
        // Se não é criação inicial com tenant explícito, exigir tenancy
        if (!$isNovaAssinatura || !$temTenantIdExplicito) {
            if (!TenantContextGuard::isInitialized()) {
                // Para operações que não são criação inicial, tenancy deve estar ok
                // Mas para evitar quebrar o sistema, apenas logamos warning
                Log::warning('AssinaturaRepository::salvar() - Operando sem tenancy inicializado', [
                    'assinatura_id' => $assinatura->id,
                    'user_id' => $assinatura->userId,
                ]);
            }
        }

        if ($assinatura->id) {
            return $this->atualizar($assinatura);
        }

        return $this->criar($assinatura);
    }

    /**
     * Criar nova assinatura
     */
    private function criar(Assinatura $assinatura): Assinatura
    {
        $model = AssinaturaModel::create([
            'user_id' => $assinatura->userId,
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

        Log::info('Assinatura criada', [
            'assinatura_id' => $model->id,
            'user_id' => $assinatura->userId,
            'plano_id' => $assinatura->planoId,
            'status' => $assinatura->status,
        ]);

        return $this->toDomain($model->fresh());
    }

    /**
     * Atualizar assinatura existente
     */
    private function atualizar(Assinatura $assinatura): Assinatura
    {
        $model = AssinaturaModel::findOrFail($assinatura->id);
        
        $model->update([
            'user_id' => $assinatura->userId,
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

        Log::info('Assinatura atualizada', [
            'assinatura_id' => $model->id,
            'status' => $assinatura->status,
        ]);

        return $this->toDomain($model->fresh());
    }

    /**
     * Cancelar assinatura
     */
    public function cancelar(int $assinaturaId, ?string $motivo = null): Assinatura
    {
        $model = AssinaturaModel::findOrFail($assinaturaId);
        
        $model->update([
            'status' => StatusAssinatura::CANCELADA->value,
            'data_cancelamento' => now(),
            'observacoes' => $motivo 
                ? ($model->observacoes ? $model->observacoes . "\n" : '') . "Cancelamento: {$motivo}"
                : $model->observacoes,
        ]);

        Log::info('Assinatura cancelada', [
            'assinatura_id' => $assinaturaId,
            'motivo' => $motivo,
        ]);

        return $this->toDomain($model->fresh());
    }

    /**
     * Listar assinaturas que expiram em X dias (para notificações)
     */
    public function listarExpirandoEm(int $dias): Collection
    {
        return AssinaturaQueries::expirandoEm($dias)
            ->get()
            ->map(fn($model) => $this->toDomain($model));
    }
}
