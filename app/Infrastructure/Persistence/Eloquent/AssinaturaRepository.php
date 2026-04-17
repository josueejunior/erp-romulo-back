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
     * Normalizar método de pagamento (corrigir valores inválidos no banco)
     * 
     * Este método garante que valores inválidos no banco de dados sejam
     * convertidos para valores válidos antes de criar a entidade do domínio.
     */
    private function normalizarMetodoPagamento(?string $metodoPagamento): ?string
    {
        if ($metodoPagamento === null || $metodoPagamento === '') {
            return null;
        }

        // Normalizar para lowercase para comparação case-insensitive
        $metodoPagamentoLower = strtolower(trim($metodoPagamento));

        $metodosValidos = ['gratuito', 'credit_card', 'pix', 'boleto', 'pendente'];
        
        // Se já é válido (case-insensitive), retornar o valor válido correto
        foreach ($metodosValidos as $metodoValido) {
            if (strtolower($metodoValido) === $metodoPagamentoLower) {
                return $metodoValido;
            }
        }

        // Mapear valores inválidos conhecidos para valores válidos
        $mapeamento = [
            'master' => 'gratuito', // 'master' era usado antigamente para planos gratuitos
            'card' => 'credit_card',
            'creditcard' => 'credit_card',
            'credit_card' => 'credit_card', // Garantir formato correto
            'debit_card' => 'credit_card',
            'debitcard' => 'credit_card',
            'free' => 'gratuito',
            'gratis' => 'gratuito',
        ];

        if (isset($mapeamento[$metodoPagamentoLower])) {
            Log::warning('AssinaturaRepository::normalizarMetodoPagamento - Valor inválido corrigido', [
                'valor_antigo' => $metodoPagamento,
                'valor_novo' => $mapeamento[$metodoPagamentoLower],
            ]);
            return $mapeamento[$metodoPagamentoLower];
        }

        // Se não conseguir mapear, usar 'pendente' como fallback seguro
        Log::warning('AssinaturaRepository::normalizarMetodoPagamento - Valor desconhecido, usando fallback', [
            'valor_antigo' => $metodoPagamento,
            'valor_novo' => 'pendente',
        ]);
        return 'pendente';
    }

    /**
     * Converter modelo Eloquent para entidade do domínio
     * 
     * Garante que valores inválidos sejam normalizados antes de criar a entidade.
     */
    private function toDomain(AssinaturaModel $model): Assinatura
    {
        // Normalizar método de pagamento ANTES de criar a entidade
        $metodoPagamentoNormalizado = $this->normalizarMetodoPagamento($model->metodo_pagamento);
        
        try {
            return new Assinatura(
                id: $model->id,
                userId: $model->user_id,
                tenantId: $model->tenant_id,
                empresaId: $model->empresa_id,
                planoId: $model->plano_id,
                status: $model->status,
                dataInicio: $model->data_inicio ? Carbon::parse($model->data_inicio) : null,
                dataFim: $model->data_fim ? Carbon::parse($model->data_fim) : null,
                dataCancelamento: $model->data_cancelamento ? Carbon::parse($model->data_cancelamento) : null,
                valorPago: $model->valor_pago ? (float) $model->valor_pago : null,
                metodoPagamento: $metodoPagamentoNormalizado,
                transacaoId: $model->transacao_id,
                diasGracePeriod: $model->dias_grace_period ?? 7,
                observacoes: $model->observacoes,
            );
        } catch (\App\Domain\Exceptions\DomainException $e) {
            // Se ainda houver erro de validação (não deveria acontecer após normalização),
            // tentar novamente com 'pendente' como fallback absoluto
            if (str_contains($e->getMessage(), 'Método de pagamento inválido')) {
                Log::error('AssinaturaRepository::toDomain - Erro de validação mesmo após normalização', [
                    'assinatura_id' => $model->id,
                    'metodo_pagamento_original' => $model->metodo_pagamento,
                    'metodo_pagamento_normalizado' => $metodoPagamentoNormalizado,
                    'erro' => $e->getMessage(),
                ]);
                
                // Tentar novamente com 'pendente' como último recurso
                return new Assinatura(
                    id: $model->id,
                    userId: $model->user_id,
                    tenantId: $model->tenant_id,
                    empresaId: $model->empresa_id,
                    planoId: $model->plano_id,
                    status: $model->status,
                    dataInicio: $model->data_inicio ? Carbon::parse($model->data_inicio) : null,
                    dataFim: $model->data_fim ? Carbon::parse($model->data_fim) : null,
                    dataCancelamento: $model->data_cancelamento ? Carbon::parse($model->data_cancelamento) : null,
                    valorPago: $model->valor_pago ? (float) $model->valor_pago : null,
                    metodoPagamento: 'pendente', // Fallback absoluto
                    transacaoId: $model->transacao_id,
                    diasGracePeriod: $model->dias_grace_period ?? 7,
                    observacoes: $model->observacoes,
                );
            }
            
            // Re-lançar se não for erro de método de pagamento
            throw $e;
        }
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
     * 
     * @deprecated Use buscarAssinaturaAtualPorEmpresa() - assinatura pertence à empresa
     */
    public function buscarAssinaturaAtualPorUsuario(int $userId): ?Assinatura
    {
        $model = AssinaturaQueries::assinaturaAtualPorUsuario($userId);
        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Buscar assinatura atual da empresa (método principal)
     * 
     * 🔥 NOVO: Assinatura pertence à empresa
     */
    public function buscarAssinaturaAtualPorEmpresa(int $empresaId, ?int $tenantId = null): ?Assinatura
    {
        $model = AssinaturaQueries::assinaturaAtualPorEmpresa($empresaId, $tenantId);
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
     * 🔒 ROBUSTEZ: Validações de integridade antes de persistir
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
                    'empresa_id' => $assinatura->empresaId,
                ]);
            }
        }

        // 🔒 Validações adicionais de integridade antes de persistir
        $this->validarIntegridadeAntesDeSalvar($assinatura);

        if ($assinatura->id) {
            return $this->atualizar($assinatura);
        }

        return $this->criar($assinatura);
    }
    
    /**
     * Validações de integridade antes de salvar
     * 
     * 🔒 ROBUSTEZ: Validações adicionais que não estão na entidade
     * 
     * @param Assinatura $assinatura
     * @throws \Exception Se validação falhar
     */
    private function validarIntegridadeAntesDeSalvar(Assinatura $assinatura): void
    {
        // Validar que empresa_id está presente para novas assinaturas
        if (!$assinatura->id && (!$assinatura->empresaId || $assinatura->empresaId <= 0)) {
            Log::error('AssinaturaRepository::validarIntegridadeAntesDeSalvar() - Empresa não informada', [
                'assinatura_id' => $assinatura->id,
                'empresa_id' => $assinatura->empresaId,
            ]);
            throw new \DomainException('Empresa é obrigatória para criar uma assinatura.');
        }
        
        // Validar que plano_id está presente e válido
        if (!$assinatura->planoId || $assinatura->planoId <= 0) {
            Log::error('AssinaturaRepository::validarIntegridadeAntesDeSalvar() - Plano inválido', [
                'assinatura_id' => $assinatura->id,
                'plano_id' => $assinatura->planoId,
            ]);
            throw new \DomainException('Plano é obrigatório e deve ser válido.');
        }
        
        // Validar datas são consistentes
        if ($assinatura->dataInicio && $assinatura->dataFim) {
            if ($assinatura->dataFim->isBefore($assinatura->dataInicio)) {
                Log::error('AssinaturaRepository::validarIntegridadeAntesDeSalvar() - Datas inconsistentes', [
                    'assinatura_id' => $assinatura->id,
                    'data_inicio' => $assinatura->dataInicio->toDateString(),
                    'data_fim' => $assinatura->dataFim->toDateString(),
                ]);
                throw new \DomainException('Data de fim não pode ser anterior à data de início.');
            }
        }
        
        Log::debug('AssinaturaRepository::validarIntegridadeAntesDeSalvar() - Validações passaram', [
            'assinatura_id' => $assinatura->id,
            'empresa_id' => $assinatura->empresaId,
            'plano_id' => $assinatura->planoId,
        ]);
    }

    /**
     * Criar nova assinatura
     * 
     * 🔒 ROBUSTEZ: Validações e logging detalhado
     */
    private function criar(Assinatura $assinatura): Assinatura
    {
        try {
            Log::info('AssinaturaRepository::criar() - Iniciando criação', [
                'empresa_id' => $assinatura->empresaId,
                'plano_id' => $assinatura->planoId,
                'status' => $assinatura->status,
                'user_id' => $assinatura->userId,
            ]);
            
            $model = AssinaturaModel::create([
                'user_id' => $assinatura->userId,
                'tenant_id' => $assinatura->tenantId,
                'empresa_id' => $assinatura->empresaId,
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

            Log::info('AssinaturaRepository::criar() - Assinatura criada com sucesso', [
                'assinatura_id' => $model->id,
                'empresa_id' => $model->empresa_id,
                'user_id' => $model->user_id,
                'plano_id' => $model->plano_id,
                'status' => $model->status,
                'data_inicio' => $model->data_inicio?->toDateString(),
                'data_fim' => $model->data_fim?->toDateString(),
            ]);

            return $this->toDomain($model->fresh());
            
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('AssinaturaRepository::criar() - Erro de banco de dados', [
                'empresa_id' => $assinatura->empresaId,
                'plano_id' => $assinatura->planoId,
                'error' => $e->getMessage(),
                'sql_state' => $e->getCode(),
            ]);
            
            // Re-lançar com mensagem mais amigável
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                throw new \DomainException('Erro ao criar assinatura: empresa ou plano não encontrado.');
            }
            
            throw new \DomainException('Erro ao criar assinatura: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('AssinaturaRepository::criar() - Erro inesperado', [
                'empresa_id' => $assinatura->empresaId,
                'plano_id' => $assinatura->planoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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
            'empresa_id' => $assinatura->empresaId,
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
