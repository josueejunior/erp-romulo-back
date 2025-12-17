<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RedisService
{
    /**
     * Cache de dados do dashboard por tenant
     */
    public static function cacheDashboard($tenantId, $data, $ttl = 300): void
    {
        $key = "dashboard:{$tenantId}";
        Cache::store('redis')->put($key, $data, $ttl);
    }

    /**
     * Obter dados do dashboard do cache
     */
    public static function getDashboard($tenantId)
    {
        $key = "dashboard:{$tenantId}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Limpar cache do dashboard
     */
    public static function clearDashboard($tenantId): void
    {
        $key = "dashboard:{$tenantId}";
        Cache::store('redis')->forget($key);
    }

    /**
     * Cache de processos por tenant com filtros
     */
    public static function cacheProcessos($tenantId, $filters, $data, $ttl = 180): void
    {
        $filterHash = md5(json_encode($filters));
        $key = "processos:{$tenantId}:{$filterHash}";
        Cache::store('redis')->put($key, $data, $ttl);
    }

    /**
     * Obter processos do cache
     */
    public static function getProcessos($tenantId, $filters)
    {
        $filterHash = md5(json_encode($filters));
        $key = "processos:{$tenantId}:{$filterHash}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Limpar cache de processos de um tenant
     */
    public static function clearProcessos($tenantId): void
    {
        $pattern = "processos:{$tenantId}:*";
        try {
            // Usar SCAN ao invés de KEYS para melhor performance
            $cursor = 0;
            do {
                $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                if (!empty($keys)) {
                    Redis::del($keys);
                }
            } while ($cursor != 0);
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar cache de processos: ' . $e->getMessage());
        }
    }

    /**
     * Cache de saldo financeiro por processo
     */
    public static function cacheSaldo($tenantId, $processoId, $data, $ttl = 600): void
    {
        $key = "saldo:{$tenantId}:{$processoId}";
        Cache::store('redis')->put($key, $data, $ttl);
    }

    /**
     * Obter saldo do cache
     */
    public static function getSaldo($tenantId, $processoId)
    {
        $key = "saldo:{$tenantId}:{$processoId}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Limpar cache de saldo de um processo
     */
    public static function clearSaldo($tenantId, $processoId): void
    {
        $key = "saldo:{$tenantId}:{$processoId}";
        Cache::store('redis')->forget($key);
    }

    /**
     * Rate limiting por IP e endpoint
     */
    public static function rateLimit($identifier, $maxAttempts = 60, $decaySeconds = 60): bool
    {
        $key = "rate_limit:{$identifier}";
        $current = Redis::incr($key);
        
        if ($current === 1) {
            Redis::expire($key, $decaySeconds);
        }
        
        return $current <= $maxAttempts;
    }

    /**
     * Obter tentativas restantes de rate limit
     */
    public static function getRateLimitRemaining($identifier, $maxAttempts = 60): int
    {
        $key = "rate_limit:{$identifier}";
        $current = (int) Redis::get($key) ?? 0;
        return max(0, $maxAttempts - $current);
    }

    /**
     * Lock distribuído para operações críticas
     */
    public static function lock($key, $ttl = 10): bool
    {
        $lockKey = "lock:{$key}";
        $result = Redis::set($lockKey, 1, 'EX', $ttl, 'NX');
        return $result === true;
    }

    /**
     * Liberar lock
     */
    public static function unlock($key): void
    {
        $lockKey = "lock:{$key}";
        Redis::del($lockKey);
    }

    /**
     * Cache de sessão de tenant ativo por usuário
     */
    public static function cacheTenantSession($userId, $tenantId, $ttl = 3600): void
    {
        $key = "user_tenant:{$userId}";
        Cache::store('redis')->put($key, $tenantId, $ttl);
    }

    /**
     * Obter tenant ativo do usuário do cache
     */
    public static function getTenantSession($userId)
    {
        $key = "user_tenant:{$userId}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Cache de lista de tenants do usuário
     */
    public static function cacheUserTenants($userId, $tenants, $ttl = 1800): void
    {
        $key = "user_tenants:{$userId}";
        Cache::store('redis')->put($key, $tenants, $ttl);
    }

    /**
     * Obter lista de tenants do usuário do cache
     */
    public static function getUserTenants($userId)
    {
        $key = "user_tenants:{$userId}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Cache de relatórios financeiros mensais
     */
    public static function cacheRelatorioFinanceiro($tenantId, $mes, $ano, $data, $ttl = 3600): void
    {
        $key = "relatorio_financeiro:{$tenantId}:{$ano}:{$mes}";
        Cache::store('redis')->put($key, $data, $ttl);
    }

    /**
     * Obter relatório financeiro do cache
     */
    public static function getRelatorioFinanceiro($tenantId, $mes, $ano)
    {
        $key = "relatorio_financeiro:{$tenantId}:{$ano}:{$mes}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Limpar cache de relatórios financeiros de um tenant
     */
    public static function clearRelatorioFinanceiro($tenantId): void
    {
        $pattern = "relatorio_financeiro:{$tenantId}:*";
        try {
            $cursor = 0;
            do {
                $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                if (!empty($keys)) {
                    Redis::del($keys);
                }
            } while ($cursor != 0);
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar cache de relatórios: ' . $e->getMessage());
        }
    }

    /**
     * Cache de calendário de eventos
     */
    public static function cacheCalendario($tenantId, $mes, $ano, $data, $ttl = 1800): void
    {
        $key = "calendario:{$tenantId}:{$ano}:{$mes}";
        Cache::store('redis')->put($key, $data, $ttl);
    }

    /**
     * Obter calendário do cache
     */
    public static function getCalendario($tenantId, $mes, $ano)
    {
        $key = "calendario:{$tenantId}:{$ano}:{$mes}";
        return Cache::store('redis')->get($key);
    }

    /**
     * Limpar cache de calendário
     */
    public static function clearCalendario($tenantId): void
    {
        $pattern = "calendario:{$tenantId}:*";
        try {
            $cursor = 0;
            do {
                $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                if (!empty($keys)) {
                    Redis::del($keys);
                }
            } while ($cursor != 0);
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar cache de calendário: ' . $e->getMessage());
        }
    }

    /**
     * Invalidar todos os caches de um tenant
     */
    public static function clearAllTenantCache($tenantId): void
    {
        $patterns = [
            "dashboard:{$tenantId}",
            "processos:{$tenantId}:*",
            "saldo:{$tenantId}:*",
            "relatorio_financeiro:{$tenantId}:*",
            "calendario:{$tenantId}:*",
        ];

        foreach ($patterns as $pattern) {
            try {
                if (str_ends_with($pattern, '*')) {
                    // Pattern com wildcard - usar SCAN
                    $cursor = 0;
                    do {
                        $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                        $cursor = $result[0];
                        $keys = $result[1];
                        if (!empty($keys)) {
                            Redis::del($keys);
                        }
                    } while ($cursor != 0);
                } else {
                    // Chave específica - deletar diretamente
                    Redis::del($pattern);
                }
            } catch (\Exception $e) {
                Log::warning("Erro ao limpar cache pattern {$pattern}: " . $e->getMessage());
            }
        }
    }

    /**
     * Estatísticas do Redis
     */
    public static function getStats(): array
    {
        try {
            $info = Redis::info();
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao obter estatísticas do Redis: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se Redis está disponível
     */
    public static function isAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            Log::warning('Redis não disponível: ' . $e->getMessage());
            return false;
        }
    }
}
