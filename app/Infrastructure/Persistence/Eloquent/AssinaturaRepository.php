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
     * Buscar assinatura atual do tenant (DEPRECATED - usar buscarAssinaturaAtualPorUsuario)
     * 
     * 🔥 REGRA DE OURO: Repository NUNCA inicializa tenancy.
     * O tenancy já deve estar pronto (inicializado pelo ApplicationContext ou pelo caller).
     * Se não estiver → bug de fluxo, não do repo.
     * 
     * ⚠️ ATENÇÃO: Este método é usado apenas em casos administrativos especiais
     * onde o caller precisa inicializar o tenancy antes de chamar.
     * Para uso normal em requisições HTTP, use buscarAssinaturaAtualPorUsuario().
     * 
     * @deprecated Use buscarAssinaturaAtualPorUsuario() em vez disso
     */
    public function buscarAssinaturaAtual(int $tenantId): ?Assinatura
    {
        \Log::debug('AssinaturaRepository::buscarAssinaturaAtual() - Iniciando busca', [
            'tenant_id' => $tenantId,
            'tenancy_initialized' => tenancy()->initialized,
            'tenant_id_atual' => tenancy()->tenant?->id,
        ]);

        // 🔥 CRÍTICO: Verificar se tenancy está inicializado e é o tenant correto
        // Se não estiver, é um bug de fluxo (caller não inicializou)
        if (!tenancy()->initialized) {
            \Log::error('AssinaturaRepository::buscarAssinaturaAtual() - Tenancy não inicializado', [
                'tenant_id' => $tenantId,
                'message' => 'Tenancy deve ser inicializado pelo caller antes de usar este método',
            ]);
            throw new \RuntimeException('Tenancy não inicializado. O caller deve inicializar o tenancy antes de chamar este método.');
        }

        $tenantAtual = tenancy()->tenant;
        if (!$tenantAtual || $tenantAtual->id !== $tenantId) {
            \Log::error('AssinaturaRepository::buscarAssinaturaAtual() - Tenant incorreto', [
                'tenant_id_solicitado' => $tenantId,
                'tenant_id_atual' => $tenantAtual?->id,
                'message' => 'O tenancy deve estar inicializado com o tenant correto',
            ]);
            throw new \RuntimeException("Tenancy inicializado com tenant incorreto. Esperado: {$tenantId}, Atual: {$tenantAtual?->id}");
        }

        $assinatura = null;

        // Buscar tenant para verificar assinatura_atual_id
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            \Log::warning('AssinaturaRepository::buscarAssinaturaAtual() - Tenant não encontrado', [
                'tenant_id' => $tenantId,
            ]);
            return null;
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
                ]);
            }
        }

        return $assinatura;
    }

    /**
     * Listar assinaturas do tenant (DEPRECATED)
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
     * 🔥 NOVO: Buscar assinatura atual do usuário
     * A assinatura pertence ao usuário, não ao tenant
     * 
     * 🔥 REGRA DE OURO: Repository NUNCA inicializa tenancy.
     * O tenancy já deve estar pronto (inicializado pelo ApplicationContext).
     * Se não estiver → bug de fluxo, não do repo.
     */
    public function buscarAssinaturaAtualPorUsuario(int $userId): ?Assinatura
    {
        \Log::debug('AssinaturaRepository::buscarAssinaturaAtualPorUsuario() - Iniciando busca', [
            'user_id' => $userId,
            'tenancy_initialized' => tenancy()->initialized,
            'tenant_id' => tenancy()->tenant?->id,
        ]);

        // 🔥 CRÍTICO: Verificar se tenancy está inicializado
        // Se não estiver, é um bug de fluxo (middleware não rodou)
        if (!tenancy()->initialized) {
            \Log::error('AssinaturaRepository::buscarAssinaturaAtualPorUsuario() - Tenancy não inicializado', [
                'user_id' => $userId,
                'message' => 'Tenancy deve ser inicializado pelo ApplicationContext antes de usar o repository',
            ]);
            throw new \RuntimeException('Tenancy não inicializado. Verifique se o middleware está configurado corretamente.');
        }

        // Buscar assinatura mais recente do usuário (não cancelada) no banco do tenant
        // O tenancy já está pronto, apenas buscar
        $model = AssinaturaModel::with('plano')
            ->where('user_id', $userId)
            ->where('status', '!=', 'cancelada')
            ->orderBy('data_fim', 'desc')
            ->orderBy('criado_em', 'desc')
            ->first();

        if ($model) {
            \Log::info('AssinaturaRepository::buscarAssinaturaAtualPorUsuario() - Assinatura encontrada', [
                'user_id' => $userId,
                'tenant_id' => tenancy()->tenant->id,
                'assinatura_id' => $model->id,
                'status' => $model->status,
                'data_fim' => $model->data_fim?->format('Y-m-d'),
            ]);
            return $this->toDomain($model);
        }

        \Log::warning('AssinaturaRepository::buscarAssinaturaAtualPorUsuario() - Nenhuma assinatura encontrada', [
            'user_id' => $userId,
            'tenant_id' => tenancy()->tenant->id,
        ]);

        return null;
    }

    /**
     * 🔥 NOVO: Listar assinaturas do usuário
     */
    public function listarPorUsuario(int $userId, array $filtros = []): Collection
    {
        $query = AssinaturaModel::where('user_id', $userId);

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
     * 🔥 REGRA DE OURO: Repository NUNCA inicializa tenancy.
     * O tenancy já deve estar pronto (inicializado pelo ApplicationContext).
     * Se não estiver → bug de fluxo, não do repo.
     */
    public function salvar(Assinatura $assinatura): Assinatura
    {
        \Log::debug('AssinaturaRepository::salvar() - Iniciando salvamento', [
            'user_id' => $assinatura->userId,
            'tenant_id' => $assinatura->tenantId,
            'assinatura_id' => $assinatura->id,
            'tenancy_initialized' => tenancy()->initialized,
            'tenant_id_atual' => tenancy()->tenant?->id,
        ]);

        // 🔥 CRÍTICO: Verificar se tenancy está inicializado
        // Se não estiver, é um bug de fluxo (middleware não rodou)
        if (!tenancy()->initialized) {
            \Log::error('AssinaturaRepository::salvar() - Tenancy não inicializado', [
                'user_id' => $assinatura->userId,
                'message' => 'Tenancy deve ser inicializado pelo ApplicationContext antes de usar o repository',
            ]);
            throw new \RuntimeException('Tenancy não inicializado. Verifique se o middleware está configurado corretamente.');
        }
        
        try {

            if ($assinatura->id) {
                // Atualizar
                $model = AssinaturaModel::findOrFail($assinatura->id);
                $model->update([
                    'user_id' => $assinatura->userId, // 🔥 NOVO: userId é obrigatório
                    'tenant_id' => $assinatura->tenantId, // Opcional
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
                    'user_id' => $assinatura->userId,
                    'tenant_id' => $assinatura->tenantId,
                    'assinatura_id' => $model->id,
                    'status' => $model->status,
                ]);
            } else {
                // Criar
                $model = AssinaturaModel::create([
                    'user_id' => $assinatura->userId, // 🔥 NOVO: userId é obrigatório
                    'tenant_id' => $assinatura->tenantId, // Opcional
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
                    'user_id' => $assinatura->userId,
                    'tenant_id' => $assinatura->tenantId,
                    'assinatura_id' => $model->id,
                    'status' => $model->status,
                    'data_fim' => $model->data_fim?->format('Y-m-d'),
                ]);
            }

            return $this->toDomain($model->fresh());
        } catch (\Exception $e) {
            \Log::error('AssinaturaRepository::salvar() - Erro ao salvar', [
                'user_id' => $assinatura->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

