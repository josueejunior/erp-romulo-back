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
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "dashboard:{$tenantId}";
            Cache::store('redis')->put($key, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Obter dados do dashboard do cache
     */
    public static function getDashboard($tenantId)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "dashboard:{$tenantId}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter dashboard do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpar cache do dashboard
     */
    public static function clearDashboard($tenantId): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "dashboard:{$tenantId}";
            Cache::store('redis')->forget($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar cache do dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Cache de processos por tenant com filtros
     */
    public static function cacheProcessos($tenantId, $filters, $data, $ttl = 180): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $filterHash = md5(json_encode($filters));
            $key = "processos:{$tenantId}:{$filterHash}";
            Cache::store('redis')->put($key, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear processos: ' . $e->getMessage());
        }
    }

    /**
     * Obter processos do cache
     */
    public static function getProcessos($tenantId, $filters)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $filterHash = md5(json_encode($filters));
            $key = "processos:{$tenantId}:{$filterHash}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter processos do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpar cache de processos de um tenant
     */
    public static function clearProcessos($tenantId): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
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
        } catch (\Error $e) {
            Log::warning('Erro ao limpar cache de processos (classe não encontrada): ' . $e->getMessage());
        }
    }

    /**
     * Cache de saldo financeiro por processo
     */
    public static function cacheSaldo($tenantId, $processoId, $data, $ttl = 600): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "saldo:{$tenantId}:{$processoId}";
            Cache::store('redis')->put($key, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear saldo: ' . $e->getMessage());
        }
    }

    /**
     * Obter saldo do cache
     */
    public static function getSaldo($tenantId, $processoId)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "saldo:{$tenantId}:{$processoId}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter saldo do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpar cache de saldo de um processo
     */
    public static function clearSaldo($tenantId, $processoId): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "saldo:{$tenantId}:{$processoId}";
            Cache::store('redis')->forget($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar cache de saldo: ' . $e->getMessage());
        }
    }

    /**
     * Rate limiting por IP e endpoint
     */
    public static function rateLimit($identifier, $maxAttempts = 60, $decaySeconds = 60): bool
    {
        if (!self::isAvailable()) {
            return true; // Permitir se Redis não estiver disponível
        }
        
        try {
            $key = "rate_limit:{$identifier}";
            $current = Redis::incr($key);
            
            if ($current === 1) {
                Redis::expire($key, $decaySeconds);
            }
            
            return $current <= $maxAttempts;
        } catch (\Exception $e) {
            Log::warning('Erro ao verificar rate limit: ' . $e->getMessage());
            return true; // Permitir em caso de erro
        }
    }

    /**
     * Obter tentativas restantes de rate limit
     */
    public static function getRateLimitRemaining($identifier, $maxAttempts = 60): int
    {
        if (!self::isAvailable()) {
            return $maxAttempts;
        }
        
        try {
            $key = "rate_limit:{$identifier}";
            $current = (int) Redis::get($key) ?? 0;
            return max(0, $maxAttempts - $current);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter rate limit remaining: ' . $e->getMessage());
            return $maxAttempts;
        }
    }

    /**
     * Limpar rate limit de um identificador específico
     */
    public static function clearRateLimit($identifier): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "rate_limit:{$identifier}";
            Redis::del($key);
            
            // 🔥 CORREÇÃO: Também limpar chaves do Laravel throttle padrão
            $prefix = config('cache.prefix', 'laravel_cache:');
            if (!empty($prefix) && !str_ends_with($prefix, ':')) {
                $prefix .= ':';
            }
            
            // Buscar e deletar todas as variações possíveis da chave
            $patterns = [
                "{$prefix}illuminate_rate_limit:{$identifier}",
                "{$prefix}throttle:{$identifier}",
                "throttle:{$identifier}",
                "laravel_cache:throttle:{$identifier}",
                "laravel_cache:illuminate_rate_limit:{$identifier}",
            ];
            
            foreach ($patterns as $pattern) {
                try {
                    $keys = Redis::keys($pattern . '*');
                    if (!empty($keys)) {
                        Redis::del($keys);
                    }
                } catch (\Exception $e) {
                    // Ignorar erros específicos
                }
            }
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar rate limit: ' . $e->getMessage());
        }
    }

    /**
     * Limpar todos os rate limits (usar com cuidado!)
     * Limpa tanto os rate limits customizados quanto os do Laravel padrão
     */
    public static function clearAllRateLimits(): void
    {
        if (!self::isAvailable()) {
            // Se Redis não estiver disponível, limpar cache do Laravel
            \Cache::flush();
            return;
        }
        
        try {
            $prefix = config('cache.prefix', 'laravel_cache:');
            if (!empty($prefix) && !str_ends_with($prefix, ':')) {
                $prefix .= ':';
            }
            
            // Limpar rate limits customizados
            $keys = Redis::keys('rate_limit:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
            
            // Limpar rate limits do Laravel padrão (vários formatos possíveis)
            $patterns = [
                $prefix . 'illuminate_rate_limit:*',
                $prefix . 'throttle:*',
                'throttle:*',
                'laravel_cache:throttle:*',
                'laravel_cache:illuminate_rate_limit:*',
            ];
            
            foreach ($patterns as $pattern) {
                try {
                    $laravelKeys = Redis::keys($pattern);
                    if (!empty($laravelKeys)) {
                        Redis::del($laravelKeys);
                    }
                } catch (\Exception $e) {
                    // Ignorar erros de padrões específicos
                }
            }
            
            // Também limpar via Cache facade para garantir
            \Cache::flush();
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar todos os rate limits: ' . $e->getMessage());
            // Fallback: limpar cache geral
            try {
                \Cache::flush();
            } catch (\Exception $e2) {
                Log::warning('Erro ao limpar cache geral: ' . $e2->getMessage());
            }
        }
    }

    /**
     * Lock distribuído para operações críticas
     */
    public static function lock($key, $ttl = 10): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        
        try {
            $lockKey = "lock:{$key}";
            $result = Redis::set($lockKey, 1, 'EX', $ttl, 'NX');
            return $result === true;
        } catch (\Exception $e) {
            Log::warning('Erro ao criar lock: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Liberar lock
     */
    public static function unlock($key): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $lockKey = "lock:{$key}";
            Redis::del($lockKey);
        } catch (\Exception $e) {
            Log::warning('Erro ao liberar lock: ' . $e->getMessage());
        }
    }

    /**
     * Cache de sessão de tenant ativo por usuário
     */
    public static function cacheTenantSession($userId, $tenantId, $ttl = 3600): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "user_tenant:{$userId}";
            Cache::store('redis')->put($key, $tenantId, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear sessão de tenant: ' . $e->getMessage());
        }
    }

    /**
     * Obter tenant ativo do usuário do cache
     */
    public static function getTenantSession($userId)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "user_tenant:{$userId}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter sessão de tenant do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache de lista de tenants do usuário
     */
    public static function cacheUserTenants($userId, $tenants, $ttl = 1800): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "user_tenants:{$userId}";
            Cache::store('redis')->put($key, $tenants, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear tenants do usuário: ' . $e->getMessage());
        }
    }

    /**
     * Obter lista de tenants do usuário do cache
     */
    public static function getUserTenants($userId)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "user_tenants:{$userId}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter tenants do usuário do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache de relatórios financeiros mensais
     */
    public static function cacheRelatorioFinanceiro($tenantId, $mes, $ano, $data, $ttl = 3600): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "relatorio_financeiro:{$tenantId}:{$ano}:{$mes}";
            Cache::store('redis')->put($key, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear relatório financeiro: ' . $e->getMessage());
        }
    }

    /**
     * Obter relatório financeiro do cache
     */
    public static function getRelatorioFinanceiro($tenantId, $mes, $ano)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "relatorio_financeiro:{$tenantId}:{$ano}:{$mes}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter relatório financeiro do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpar cache de relatórios financeiros de um tenant
     */
    public static function clearRelatorioFinanceiro($tenantId): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
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
        } catch (\Error $e) {
            Log::warning('Erro ao limpar cache de relatórios (classe não encontrada): ' . $e->getMessage());
        }
    }

    /**
     * Cache de calendário de eventos
     */
    public static function cacheCalendario($tenantId, $mes, $ano, $data, $ttl = 1800): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "calendario:{$tenantId}:{$ano}:{$mes}";
            Cache::store('redis')->put($key, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear calendário: ' . $e->getMessage());
        }
    }

    /**
     * Obter calendário do cache
     */
    public static function getCalendario($tenantId, $mes, $ano)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "calendario:{$tenantId}:{$ano}:{$mes}";
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning('Erro ao obter calendário do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpar cache de calendário
     */
    public static function clearCalendario($tenantId): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
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
        } catch (\Error $e) {
            Log::warning('Erro ao limpar cache de calendário (classe não encontrada): ' . $e->getMessage());
        }
    }

    /**
     * Invalidar todos os caches de um tenant
     */
    public static function clearAllTenantCache($tenantId): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
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
            } catch (\Error $e) {
                Log::warning("Erro ao limpar cache pattern {$pattern} (classe não encontrada): " . $e->getMessage());
            }
        }
    }

    /**
     * Estatísticas do Redis
     */
    public static function getStats(): array
    {
        if (!self::isAvailable()) {
            return [];
        }
        
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
        } catch (\Error $e) {
            Log::error('Erro ao obter estatísticas do Redis (classe não encontrada): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Cache de mapeamento email -> tenant_id para login rápido
     * TTL padrão: 1 hora (3600 segundos)
     */
    public static function cacheEmailToTenant(string $email, string $tenantId, int $ttl = 3600): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "email_tenant:" . md5(strtolower($email));
            Cache::store('redis')->put($key, $tenantId, $ttl);
            Log::debug("Email -> Tenant cacheado", [
                'email' => $email,
                'tenant_id' => $tenantId,
                'ttl' => $ttl
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear email -> tenant: ' . $e->getMessage());
        }
    }

    /**
     * Obter tenant_id do cache pelo email
     */
    public static function getTenantByEmail(string $email): ?string
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "email_tenant:" . md5(strtolower($email));
            $tenantId = Cache::store('redis')->get($key);
            
            if ($tenantId) {
                Log::debug("Tenant encontrado no cache", [
                    'email' => $email,
                    'tenant_id' => $tenantId
                ]);
            }
            
            return $tenantId;
        } catch (\Exception $e) {
            Log::warning('Erro ao obter tenant do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Invalidar cache de email -> tenant_id
     * Útil quando usuário é criado, atualizado ou deletado
     */
    public static function invalidateEmailToTenant(string $email): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $key = "email_tenant:" . md5(strtolower($email));
            Cache::store('redis')->forget($key);
            Log::debug("Cache de email -> tenant invalidado", ['email' => $email]);
        } catch (\Exception $e) {
            Log::warning('Erro ao invalidar cache de email -> tenant: ' . $e->getMessage());
        }
    }

    /**
     * Cache de resultado de login (email + senha hash -> tenant_id + user_id)
     * TTL padrão: 30 minutos (1800 segundos)
     * Usa hash da senha para evitar cachear senhas em texto claro
     */
    public static function cacheLoginResult(string $email, string $passwordHash, array $result, int $ttl = 1800): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            // Usar hash da senha para criar chave única
            $key = "login:" . md5(strtolower($email) . ':' . $passwordHash);
            Cache::store('redis')->put($key, $result, $ttl);
            Log::debug("Resultado de login cacheado", [
                'email' => $email,
                'ttl' => $ttl
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao cachear resultado de login: ' . $e->getMessage());
        }
    }

    /**
     * Obter resultado de login do cache
     */
    public static function getLoginResult(string $email, string $passwordHash): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $key = "login:" . md5(strtolower($email) . ':' . $passwordHash);
            $result = Cache::store('redis')->get($key);
            
            if ($result) {
                Log::debug("Resultado de login encontrado no cache", ['email' => $email]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::warning('Erro ao obter resultado de login do cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Invalidar todos os caches de login de um email
     * Útil quando senha é alterada
     */
    public static function invalidateLoginCache(string $email): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            // Buscar todas as chaves de login para este email
            $pattern = "login:" . md5(strtolower($email) . ':*');
            $cursor = 0;
            
            do {
                $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                
                if (!empty($keys)) {
                    Redis::del($keys);
                }
            } while ($cursor != 0);
            
            Log::debug("Cache de login invalidado", ['email' => $email]);
        } catch (\Exception $e) {
            Log::warning('Erro ao invalidar cache de login: ' . $e->getMessage());
        }
    }

    /**
     * Limpar todos os caches relacionados a autenticação
     */
    public static function clearAuthCache(): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $patterns = ['email_tenant:*', 'login:*', 'user_tenant:*'];
            
            foreach ($patterns as $pattern) {
                $cursor = 0;
                do {
                    $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    
                    if (!empty($keys)) {
                        Redis::del($keys);
                    }
                } while ($cursor != 0);
            }
            
            Log::info("Cache de autenticação limpo");
        } catch (\Exception $e) {
            Log::warning('Erro ao limpar cache de autenticação: ' . $e->getMessage());
        }
    }

    /**
     * Verificar se Redis está disponível
     */
    /**
     * Métodos genéricos para cache (get/set)
     */
    public static function get(string $key)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            return Cache::store('redis')->get($key);
        } catch (\Exception $e) {
            Log::warning("Erro ao obter cache '{$key}': " . $e->getMessage());
            return null;
        }
    }

    public static function set(string $key, $value, int $ttl = 300): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            Cache::store('redis')->put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning("Erro ao salvar cache '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Salvar cache com tags (mais eficiente para invalidação)
     * 
     * @param string $key Chave do cache
     * @param mixed $value Valor a ser cacheado
     * @param array $tags Array de tags (ex: ["tenant_1", "empresa_2"])
     * @param int $ttl Tempo de vida em segundos
     */
    public static function setWithTags(string $key, $value, array $tags, int $ttl = 300): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $store = Cache::store('redis');
            if (method_exists($store, 'tags')) {
                $store->tags($tags)->put($key, $value, $ttl);
            } else {
                // Fallback para método sem tags
                self::set($key, $value, $ttl);
            }
        } catch (\Exception $e) {
            Log::warning("Erro ao salvar cache com tags '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Obter cache com tags
     * 
     * @param string $key Chave do cache
     * @param array $tags Array de tags (ex: ["tenant_1", "empresa_2"])
     * @return mixed
     */
    public static function getWithTags(string $key, array $tags)
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $store = Cache::store('redis');
            if (method_exists($store, 'tags')) {
                return $store->tags($tags)->get($key);
            } else {
                // Fallback para método sem tags
                return self::get($key);
            }
        } catch (\Exception $e) {
            Log::warning("Erro ao obter cache com tags '{$key}': " . $e->getMessage());
            return null;
        }
    }

    public static function forget(string $key): void
    {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            Cache::store('redis')->forget($key);
        } catch (\Exception $e) {
            Log::warning("Erro ao remover cache '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Limpar cache usando Tags (método mais eficiente)
     * 
     * @param array $tags Array de tags (ex: ["tenant_1", "empresa_2"])
     * @return bool Sucesso
     */
    public static function forgetByTags(array $tags): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        
        try {
            // Verificar se o driver de cache suporta tags
            $store = Cache::store('redis');
            if (method_exists($store, 'tags')) {
                $store->tags($tags)->flush();
                
                Log::info('RedisService::forgetByTags() - Cache limpo usando tags', [
                    'tags' => $tags,
                ]);
                
                return true;
            }
            
            Log::warning('RedisService::forgetByTags() - Driver não suporta tags, usando fallback');
            return false;
        } catch (\Exception $e) {
            Log::warning("Erro ao limpar cache por tags", [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Limpar cache por padrão (wildcard) - Método robusto com múltiplas estratégias
     * 
     * @param string $pattern Padrão da chave (ex: "tenant_1:empresa_2:*")
     * @return int Número de chaves deletadas
     */
    public static function forgetByPattern(string $pattern): int
    {
        if (!self::isAvailable()) {
            return 0;
        }
        
        try {
            // ESTRATÉGIA 1: Tentar usar Cache Tags (mais eficiente)
            // Extrair tenant_id e empresa_id do pattern se possível
            if (preg_match('/tenant_(\d+):empresa_(\d+):\*/', $pattern, $matches)) {
                $tenantId = $matches[1];
                $empresaId = $matches[2];
                
                if (self::forgetByTags(["tenant_{$tenantId}", "empresa_{$empresaId}"])) {
                    Log::info('RedisService::forgetByPattern() - Cache limpo usando tags', [
                        'pattern' => $pattern,
                        'tags' => ["tenant_{$tenantId}", "empresa_{$empresaId}"],
                    ]);
                    return 1; // Retorna 1 para indicar sucesso (não sabemos quantas chaves foram deletadas)
                }
            }
            
            // ESTRATÉGIA 2: Usar SCAN em vez de KEYS (mais seguro em produção)
            $cachePrefix = config('cache.prefix', '');
            if (!empty($cachePrefix) && !str_ends_with($cachePrefix, ':')) {
                $cachePrefix .= ':';
            }
            
            $patternWithPrefix = $cachePrefix . $pattern;
            
            Log::debug('RedisService::forgetByPattern() - Buscando chaves com SCAN', [
                'pattern' => $pattern,
                'pattern_with_prefix' => $patternWithPrefix,
                'cache_prefix' => $cachePrefix,
            ]);
            
            $allKeys = [];
            $cursor = 0;
            
            // Usar SCAN em vez de KEYS (mais seguro e eficiente)
            do {
                try {
                    // Converter padrão wildcard para padrão SCAN
                    $scanPattern = str_replace('*', '*', $patternWithPrefix);
                    
                    $result = Redis::scan($cursor, [
                        'match' => $scanPattern,
                        'count' => 100, // Processar 100 chaves por vez
                    ]);
                    
                    $cursor = $result[0];
                    $keys = $result[1] ?? [];
                    
                    if (!empty($keys)) {
                        $allKeys = array_merge($allKeys, $keys);
                        Log::debug('RedisService::forgetByPattern() - Chaves encontradas (SCAN)', [
                            'keys_found_this_iteration' => count($keys),
                            'total_keys_so_far' => count($allKeys),
                            'cursor' => $cursor,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao fazer SCAN', ['error' => $e->getMessage()]);
                    break;
                }
            } while ($cursor != 0);
            
            // ESTRATÉGIA 3: Fallback para KEYS (apenas se SCAN não funcionar)
            if (empty($allKeys)) {
                try {
                    // Tentar com prefixo
                    $keys = Redis::keys($patternWithPrefix);
                    if (!empty($keys)) {
                        $allKeys = array_merge($allKeys, $keys);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao usar KEYS com prefixo', ['error' => $e->getMessage()]);
                }
                
                // Tentar sem prefixo
                if (empty($allKeys)) {
                    try {
                        $keys = Redis::keys($pattern);
                        if (!empty($keys)) {
                            $allKeys = array_merge($allKeys, $keys);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Erro ao usar KEYS sem prefixo', ['error' => $e->getMessage()]);
                    }
                }
            }
            
            // ESTRATÉGIA 4: Usar conexão direta do Cache store
            if (empty($allKeys)) {
                try {
                    $store = Cache::store('redis');
                    $redisConnection = $store->getStore()->connection();
                    
                    // Tentar SCAN na conexão do store
                    $cursor = 0;
                    do {
                        $result = $redisConnection->scan($cursor, [
                            'match' => $patternWithPrefix,
                            'count' => 100,
                        ]);
                        $cursor = $result[0];
                        $keys = $result[1] ?? [];
                        if (!empty($keys)) {
                            $allKeys = array_merge($allKeys, $keys);
                        }
                    } while ($cursor != 0);
                } catch (\Exception $e) {
                    Log::warning('Erro ao usar conexão do Cache store', ['error' => $e->getMessage()]);
                }
            }
            
            if (!empty($allKeys)) {
                // Remover duplicatas
                $allKeys = array_unique($allKeys);
                
                // Deletar em lotes para evitar timeout
                $deleted = 0;
                $batchSize = 100;
                $batches = array_chunk($allKeys, $batchSize);
                
                foreach ($batches as $batch) {
                    try {
                        $deleted += Redis::del($batch);
                    } catch (\Exception $e) {
                        Log::warning('Erro ao deletar lote de chaves', [
                            'batch_size' => count($batch),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                Log::info('RedisService::forgetByPattern() - Cache limpo', [
                    'pattern' => $pattern,
                    'pattern_with_prefix' => $patternWithPrefix,
                    'keys_found' => count($allKeys),
                    'keys_deleted' => $deleted,
                    'batches' => count($batches),
                    'sample_keys' => array_slice($allKeys, 0, 3),
                ]);
                
                return $deleted;
            }
            
            Log::warning('RedisService::forgetByPattern() - Nenhuma chave encontrada', [
                'pattern' => $pattern,
                'pattern_with_prefix' => $patternWithPrefix,
            ]);
            
            return 0;
        } catch (\Exception $e) {
            Log::warning("Erro ao limpar cache por padrão '{$pattern}'", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 0;
        }
    }

    public static function isAvailable(): bool
    {
        try {
            // Verificar se a classe Predis existe
            if (!class_exists('Predis\Client')) {
                return false;
            }
            
            // Tentar fazer ping no Redis
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            Log::warning('Redis não disponível: ' . $e->getMessage());
            return false;
        } catch (\Error $e) {
            // Capturar erros de classe não encontrada
            Log::warning('Redis não disponível (classe não encontrada): ' . $e->getMessage());
            return false;
        }
    }
}

