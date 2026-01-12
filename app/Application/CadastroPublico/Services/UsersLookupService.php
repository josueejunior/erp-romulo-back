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
            // Verificar se já existe registro para este email+tenant+user
            $existentes = $this->lookupRepository->buscarAtivosPorEmail($email);
            $existente = null;
            
            foreach ($existentes as $e) {
                if ($e->tenantId === $tenantId && $e->userId === $userId) {
                    $existente = $e;
                    break;
                }
            }
            
            if ($existente) {
                // Atualizar registro existente
                $lookup = new UserLookup(
                    id: $existente->id,
                    email: $email,
                    cnpj: $cnpjLimpo,
                    tenantId: $tenantId,
                    userId: $userId,
                    empresaId: $empresaId,
                    status: 'ativo',
                );
                
                $this->lookupRepository->atualizar($lookup);
                
                Log::info('UsersLookupService::registrar - Registro atualizado', [
                    'id' => $existente->id,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'email' => $email,
                ]);
            } else {
                // Criar novo registro
                $lookup = new UserLookup(
                    id: null,
                    email: $email,
                    cnpj: $cnpjLimpo,
                    tenantId: $tenantId,
                    userId: $userId,
                    empresaId: $empresaId,
                    status: 'ativo',
                );
                
                $this->lookupRepository->criar($lookup);
                
                Log::info('UsersLookupService::registrar - Registro criado', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'email' => $email,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('UsersLookupService::registrar - Erro ao criar/atualizar registro', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Não quebrar o fluxo - apenas logar erro
            // O registro pode ser criado posteriormente via comando
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




