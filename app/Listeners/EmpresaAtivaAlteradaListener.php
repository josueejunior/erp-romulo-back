<?php

namespace App\Listeners;

use App\Domain\Auth\Events\EmpresaAtivaAlterada;
use App\Services\RedisService;
use Illuminate\Support\Facades\Log;

/**
 * Listener para limpar cache quando empresa ativa é alterada
 * Responsabilidade de infraestrutura (cache) não deve estar no Controller
 */
class EmpresaAtivaAlteradaListener
{
    /**
     * Handle the event.
     */
    public function handle(EmpresaAtivaAlterada $event): void
    {
        try {
            // Limpeza de cache cirúrgica usando Tags (mais eficiente)
            $tags = ["tenant_{$event->tenantId}", "empresa_{$event->empresaIdNova}"];
            $tagsSuccess = RedisService::forgetByTags($tags);
            
            if (!$tagsSuccess) {
                // Fallback para pattern matching
                $pattern = "tenant_{$event->tenantId}:empresa_{$event->empresaIdNova}:*";
                $totalKeysDeleted = RedisService::forgetByPattern($pattern);
                
                Log::info('Cache invalidado para troca de empresa (fallback pattern)', [
                    'pattern' => $pattern,
                    'total_keys_deleted' => $totalKeysDeleted,
                    'user_id' => $event->userId,
                    'empresa_id_antiga' => $event->empresaIdAntiga,
                    'empresa_id_nova' => $event->empresaIdNova,
                ]);
            } else {
                Log::info('Cache invalidado para troca de empresa (tags)', [
                    'tags' => $tags,
                    'user_id' => $event->userId,
                    'empresa_id_antiga' => $event->empresaIdAntiga,
                    'empresa_id_nova' => $event->empresaIdNova,
                ]);
            }
        } catch (\Exception $e) {
            // Log erro mas não quebra o fluxo
            Log::error('Erro ao limpar cache após troca de empresa ativa', [
                'error' => $e->getMessage(),
                'user_id' => $event->userId,
                'empresa_id_nova' => $event->empresaIdNova,
            ]);
        }
    }
}


