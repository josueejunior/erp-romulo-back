<?php

namespace App\Domain\Assinatura\Repositories;

use App\Domain\Assinatura\Entities\Assinatura;
use Illuminate\Support\Collection;

/**
 * Interface do Repository de Assinatura
 * 
 * Define contrato para persistência de assinaturas.
 * A validação de contexto (TenantContextGuard) deve ser feita
 * pelo Application Service antes de chamar os métodos.
 */
interface AssinaturaRepositoryInterface
{
    // ==================== BUSCA POR ID ====================

    /**
     * Buscar assinatura por ID
     */
    public function buscarPorId(int $id): ?Assinatura;

    // ==================== BUSCA POR USUÁRIO (PRINCIPAL) ====================

    /**
     * Buscar assinatura atual do usuário (mais recente válida)
     */
    public function buscarAssinaturaAtualPorUsuario(int $userId): ?Assinatura;

    /**
     * Listar assinaturas ativas do usuário
     */
    public function listarAtivasPorUsuario(int $userId): Collection;

    /**
     * Listar histórico completo de assinaturas do usuário
     */
    public function listarHistoricoPorUsuario(int $userId): Collection;

    /**
     * Verificar se usuário tem assinatura válida
     */
    public function usuarioTemAssinaturaValida(int $userId): bool;

    // ==================== BUSCA POR TRANSAÇÃO ====================

    /**
     * Buscar assinatura por transação (para webhooks de pagamento)
     */
    public function buscarPorTransacao(string $transacaoId): ?Assinatura;

    // ==================== OPERAÇÕES DE ESCRITA ====================

    /**
     * Salvar assinatura (criar ou atualizar)
     */
    public function salvar(Assinatura $assinatura): Assinatura;

    /**
     * Cancelar assinatura
     */
    public function cancelar(int $assinaturaId, ?string $motivo = null): Assinatura;

    // ==================== QUERIES PARA NOTIFICAÇÕES/ADMIN ====================

    /**
     * Listar assinaturas que expiram em X dias (para envio de alertas)
     */
    public function listarExpirandoEm(int $dias): Collection;

    // ==================== MÉTODOS DEPRECATED ====================

    /**
     * @deprecated Use buscarAssinaturaAtualPorUsuario()
     */
    public function buscarAssinaturaAtual(int $tenantId): ?Assinatura;

    /**
     * @deprecated Use listarAtivasPorUsuario() ou listarHistoricoPorUsuario()
     */
    public function listarPorTenant(int $tenantId, array $filtros = []): Collection;

    /**
     * @deprecated Use listarAtivasPorUsuario() ou listarHistoricoPorUsuario()
     */
    public function listarPorUsuario(int $userId, array $filtros = []): Collection;

    // ==================== ACESSO A MODELOS (CASOS ESPECIAIS) ====================

    /**
     * Buscar modelo Eloquent por ID
     * Use apenas quando necessário (ex: relacionamentos, updates específicos)
     */
    public function buscarModeloPorId(int $id): ?\App\Modules\Assinatura\Models\Assinatura;

    /**
     * Buscar modelo Eloquent por transacao_id
     */
    public function buscarModeloPorTransacaoId(string $transacaoId): ?\App\Modules\Assinatura\Models\Assinatura;
}
