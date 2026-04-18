<?php

namespace App\Domain\Assinatura\Queries;

use App\Domain\Assinatura\Enums\StatusAssinatura;
use App\Modules\Assinatura\Models\Assinatura as AssinaturaModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query Objects para Assinatura
 * 
 * Centraliza queries complexas, evitando duplicação e facilitando manutenção.
 * Cada método retorna um Builder ou resultado final.
 */
final class AssinaturaQueries
{
    public static function assinaturaAtualPorUsuario(int $userId): ?AssinaturaModel
    {
        return self::baseQueryValida()
            ->where('user_id', $userId)
            ->reorder()
            ->orderByRaw("CASE 
                WHEN status IN ('ativa', 'trial') THEN 1 
                WHEN status IN ('aguardando_pagamento', 'pendente') THEN 2 
                ELSE 3 
            END ASC")
            ->orderByDesc('data_fim')
            ->first();
    }

    /**
     * Busca assinatura atual por empresa (mais recente válida)
     * 
     * 🔥 NOVO: Assinatura pertence à empresa, não ao usuário
     * 🔥 CORREÇÃO: Busca por empresa_id E tenant_id para ser único em multi-tenant
     *    Fallback: Busca apenas por empresa_id ou apenas por tenant_id (legado)
     *    Retorna a assinatura mais recente (por data_fim e id)
     */
    public static function assinaturaAtualPorEmpresa(int $empresaId, ?int $tenantId = null): ?AssinaturaModel
    {
        // 🔥 MELHORIA: Tentar obter tenantId do contexto se não fornecido
        if ($tenantId === null && tenancy()->initialized) {
            $tenantId = (int) tenancy()->tenant->id;
        }

        return self::baseQueryValida()
            ->where(function($query) use ($empresaId, $tenantId) {
                if ($tenantId) {
                    // Busca precisa: ambos devem bater
                    $query->where('empresa_id', $empresaId)
                          ->where('tenant_id', $tenantId);
                } else {
                    // Legado ou sem tenant: tenta um ou outro
                    $query->where('empresa_id', $empresaId)
                          ->orWhere('tenant_id', $empresaId);
                }
            })
            // 🔥 PRIORIDADE: Ativa/Trial vence Aguardando Pagamento
            ->reorder()
            ->orderByRaw("CASE 
                WHEN status IN ('ativa', 'trial') THEN 1 
                WHEN status IN ('aguardando_pagamento', 'pendente') THEN 2 
                ELSE 3 
            END ASC")
            ->orderByDesc('data_fim') // Desempate pela data final
            ->first();
    }

    /**
     * Busca assinatura atual por tenant (mais recente válida)
     */
    public static function assinaturaAtualPorTenant(int|string $tenantId): ?AssinaturaModel
    {
        return self::baseQueryValida()
            ->where('tenant_id', $tenantId)
            ->reorder()
            ->orderByRaw("CASE 
                WHEN status IN ('ativa', 'trial') THEN 1 
                WHEN status IN ('aguardando_pagamento', 'pendente') THEN 2 
                ELSE 3 
            END ASC")
            ->orderByDesc('data_fim')
            ->first();
    }

    /**
     * Lista assinaturas ativas de um usuário
     */
    public static function ativasPorUsuario(int $userId): Builder
    {
        return AssinaturaModel::with('plano')
            ->where('user_id', $userId)
            ->whereIn('status', StatusAssinatura::statusAtivos())
            ->orderByDesc('data_fim');
    }

    /**
     * Lista histórico completo de assinaturas do usuário
     */
    public static function historicoPorUsuario(int $userId): Builder
    {
        return AssinaturaModel::with('plano')
            ->where('user_id', $userId)
            ->orderByDesc('criado_em');
    }

    /**
     * Lista assinaturas que expiram em X dias
     */
    public static function expirandoEm(int $dias): Builder
    {
        $dataLimite = now()->addDays($dias);

        return AssinaturaModel::with(['plano', 'user'])
            ->whereIn('status', StatusAssinatura::statusAtivos())
            ->whereDate('data_fim', '<=', $dataLimite)
            ->whereDate('data_fim', '>=', now())
            ->orderBy('data_fim');
    }

    /**
     * Busca assinatura por transação (pagamento)
     */
    public static function porTransacao(string $transacaoId): ?AssinaturaModel
    {
        return AssinaturaModel::with('plano')
            ->where('transacao_id', $transacaoId)
            ->first();
    }

    /**
     * Conta assinaturas ativas por plano
     */
    public static function contarAtivasPorPlano(int $planoId): int
    {
        return AssinaturaModel::where('plano_id', $planoId)
            ->whereIn('status', StatusAssinatura::statusAtivos())
            ->count();
    }

    /**
     * Verifica se usuário tem assinatura válida
     * 
     * @deprecated Use empresaTemAssinaturaValida() - assinatura pertence à empresa
     */
    public static function usuarioTemAssinaturaValida(int $userId): bool
    {
        return AssinaturaModel::where('user_id', $userId)
            ->whereIn('status', StatusAssinatura::statusAtivos())
            ->whereDate('data_fim', '>=', now())
            ->exists();
    }

    /**
     * Verifica se empresa tem assinatura válida
     * 
     * 🔥 NOVO: Assinatura pertence à empresa
     * 🔥 CORREÇÃO: Também verifica por tenant_id para compatibilidade
     */
    public static function empresaTemAssinaturaValida(int $empresaId): bool
    {
        // Buscar por empresa_id OU tenant_id
        return AssinaturaModel::where(function($query) use ($empresaId) {
                $query->where('empresa_id', $empresaId)
                      ->orWhere('tenant_id', $empresaId);
            })
            ->whereIn('status', StatusAssinatura::statusAtivos())
            ->whereDate('data_fim', '>=', now())
            ->exists();
    }

    /**
     * Lista assinaturas em grace period (expiradas mas dentro do período de carência)
     */
    public static function emGracePeriod(): Builder
    {
        return AssinaturaModel::with(['plano', 'user', 'tenant'])
            ->where('status', StatusAssinatura::ATIVA->value)
            ->whereDate('data_fim', '<', now())
            ->whereRaw("(data_fim + (dias_grace_period || ' days')::interval) >= NOW()");
    }

    // ==================== BUILDERS PRIVADOS ====================

    /**
     * Query base para assinaturas válidas (não canceladas/expiradas)
     * 
     * 🔥 IMPORTANTE: Inclui status 'ativa', 'trial', 'pendente', 'aguardando_pagamento'
     * Exclui apenas 'cancelada' e 'expirada'
     */
    private static function baseQueryValida(): Builder
    {
        return AssinaturaModel::with('plano')
            ->whereNotIn('status', StatusAssinatura::statusExcluidos())
            ->orderByDesc('data_fim')
            ->orderByDesc('criado_em');
    }
}

