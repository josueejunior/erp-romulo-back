<?php

namespace App\Modules\Orgao\Observers;

use App\Modules\Orgao\Models\Orgao;
use App\Services\RedisService;

class OrgaoObserver
{
    /**
     * Handle the Orgao "created" event.
     */
    public function created(Orgao $orgao): void
    {
        $this->clearCache($orgao);
    }

    /**
     * Handle the Orgao "updated" event.
     */
    public function updated(Orgao $orgao): void
    {
        $this->clearCache($orgao);
    }

    /**
     * Handle the Orgao "deleted" event.
     */
    public function deleted(Orgao $orgao): void
    {
        $this->clearCache($orgao);
    }

    /**
     * Handle the Orgao "restored" event.
     */
    public function restored(Orgao $orgao): void
    {
        $this->clearCache($orgao);
    }

    /**
     * Limpar caches relacionados ao órgão
     */
    protected function clearCache(Orgao $orgao): void
    {
        if (!RedisService::isAvailable()) {
            return;
        }

        $tenantId = tenancy()->tenant?->id;
        if (!$tenantId) {
            return;
        }

        // Limpar cache de dashboard
        RedisService::clearDashboard($tenantId);
        
        // Limpar cache específico de órgãos
        $cacheKey = "orgaos:{$tenantId}:{$orgao->empresa_id}";
        RedisService::forget($cacheKey);
        
        // Limpar cache de listagem
        $listCacheKey = "orgaos:list:{$tenantId}:{$orgao->empresa_id}";
        RedisService::forget($listCacheKey);
        
        // Limpar todos os caches de órgãos com padrão (para garantir)
        $pattern = "orgaos:{$tenantId}:{$orgao->empresa_id}:*";
        try {
            $cursor = 0;
            do {
                $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                if (!empty($keys)) {
                    \Illuminate\Support\Facades\Redis::del($keys);
                }
            } while ($cursor != 0);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Erro ao limpar cache de órgãos: ' . $e->getMessage());
        }
    }
}

