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
    /**
     * Busca assinatura atual por usuário (mais recente válida)
     */
    public static function assinaturaAtualPorUsuario(int $userId): ?AssinaturaModel
    {
        return self::baseQueryValida()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Busca assinatura atual por tenant (mais recente válida)
     */
    public static function assinaturaAtualPorTenant(int|string $tenantId): ?AssinaturaModel
    {
        return self::baseQueryValida()
            ->where('tenant_id', $tenantId)
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
            ->orderByDesc('created_at');
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
     */
    public static function usuarioTemAssinaturaValida(int $userId): bool
    {
        return AssinaturaModel::where('user_id', $userId)
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
            ->whereRaw('DATE_ADD(data_fim, INTERVAL dias_grace_period DAY) >= NOW()');
    }

    // ==================== BUILDERS PRIVADOS ====================

    /**
     * Query base para assinaturas válidas (não canceladas/expiradas)
     */
    private static function baseQueryValida(): Builder
    {
        return AssinaturaModel::with('plano')
            ->whereNotIn('status', StatusAssinatura::statusExcluidos())
            ->orderByDesc('data_fim')
            ->orderByDesc('created_at');
    }
}

