<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\Services;

use App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface;
use App\Domain\UsersLookup\Entities\UserLookup;
use Illuminate\Support\Facades\Log;

/**
 * Service para gerenciar registros na tabela users_lookup
 * 
 * ⚡ Responsável por criar, atualizar e gerenciar registros na tabela global de lookup
 */
final class UsersLookupService
{
    public function __construct(
        private readonly UserLookupRepositoryInterface $lookupRepository,
    ) {}
    
    /**
     * Cria ou atualiza um registro em users_lookup
     * 
     * Se já existir um registro com mesmo email+tenantId+userId, atualiza.
     * Caso contrário, cria novo registro.
     */
    public function registrar(
        int $tenantId,
        int $userId,
        ?int $empresaId,
        string $email,
        string $cnpj
    ): void {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        
        Log::debug('UsersLookupService::registrar - Iniciando', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'empresa_id' => $empresaId,
            'email' => $email,
            'cnpj' => $cnpjLimpo,
        ]);
        
        try {
            // 🔥 SOLUÇÃO PROFUNDA: O método criar() do repository agora usa updateOrCreate
            // baseado na constraint única (cnpj, tenant_id). Isso garante idempotência
            // e evita Unique Violation mesmo em cenários de concorrência ou chamadas duplicadas.
            //
            // Não precisamos mais verificar manualmente se existe - o repository faz isso
            // de forma atômica usando UPSERT (INSERT ... ON CONFLICT DO UPDATE).
            $lookup = new UserLookup(
                id: null, // Será definido pelo repository após upsert
                email: $email,
                cnpj: $cnpjLimpo,
                tenantId: $tenantId,
                userId: $userId,
                empresaId: $empresaId,
                status: 'ativo',
            );
            
            $lookupSalvo = $this->lookupRepository->criar($lookup);
            
            Log::info('UsersLookupService::registrar - Registro processado com sucesso', [
                'id' => $lookupSalvo->id,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'email' => $email,
                'cnpj' => $cnpjLimpo,
            ]);
        } catch (\Exception $e) {
            Log::error('UsersLookupService::registrar - Erro ao criar/atualizar registro', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'email' => $email,
                'cnpj' => $cnpjLimpo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // 🔥 IMPORTANTE: Re-lançar exceção para que a transação seja abortada corretamente
            // Se o erro for de constraint única, o updateOrCreate deveria ter tratado.
            // Se chegou aqui, é um erro inesperado que precisa ser investigado.
            throw $e;
        }
    }
    
    /**
     * Inativa um registro em users_lookup
     */
    public function inativar(string $email, int $tenantId, int $userId): void
    {
        Log::debug('UsersLookupService::inativar - Iniciando', [
            'email' => $email,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);
        
        try {
            $existentes = $this->lookupRepository->buscarAtivosPorEmail($email);
            
            foreach ($existentes as $existente) {
                if ($existente->tenantId === $tenantId && $existente->userId === $userId) {
                    $this->lookupRepository->marcarComoInativo($existente->id);
                    
                    Log::info('UsersLookupService::inativar - Registro inativado', [
                        'id' => $existente->id,
                        'email' => $email,
                        'tenant_id' => $tenantId,
                    ]);
                    return;
                }
            }
            
            Log::warning('UsersLookupService::inativar - Registro não encontrado', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            Log::error('UsersLookupService::inativar - Erro ao inativar registro', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Busca tenant_id e user_id por email (para login)
     * 
     * Retorna array de UserLookup porque um email pode pertencer a múltiplos tenants
     */
    public function encontrarPorEmail(string $email): array
    {
        return $this->lookupRepository->buscarAtivosPorEmail($email);
    }
}






