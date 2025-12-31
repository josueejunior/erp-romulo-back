<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Use Case: Obter Dados do Usuário Autenticado
 */
class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna array com dados do usuário, tenant e empresa
     */
    public function executar(Authenticatable $user): array
    {
        // 🔥 IMPORTANTE: Priorizar tenant_id do header X-Tenant-ID (fonte de verdade)
        // O middleware já inicializou o tenant baseado no header
        // Se o tenant já está inicializado, usar ele (garante que está correto)
        $tenant = null;
        $tenantId = null;
        
        // Prioridade 1: Usar tenant já inicializado pelo middleware (mais confiável)
        if (tenancy()->initialized && tenancy()->tenant) {
            $tenant = tenancy()->tenant;
            $tenantId = $tenant->id;
        } else {
            // Prioridade 2: Tentar obter do header (se middleware não inicializou)
            $request = request();
            if ($request && $request->header('X-Tenant-ID')) {
                $tenantId = (int) $request->header('X-Tenant-ID');
                $tenant = Tenant::find($tenantId);
            } else {
                // Prioridade 3: Fallback para token (pode estar desatualizado)
                if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                    $abilities = $user->currentAccessToken()->abilities;
                    $tenantId = $abilities['tenant_id'] ?? null;
                    
                    if ($tenantId) {
                        $tenant = Tenant::find($tenantId);
                    }
                }
            }
        }

        // Buscar empresa ativa e lista de empresas
        $empresaAtiva = null;
        $empresasList = [];
        if ($tenant) {
            tenancy()->initialize($tenant);
            try {
                // Buscar todas as empresas do usuário
                $empresas = $this->userRepository->buscarEmpresas($user->id);
                
                // Transformar para formato esperado pelo frontend
                // $empresas retorna objetos Empresa do domínio com razaoSocial (camelCase)
                // Remover duplicatas baseado no ID da empresa
                $empresasUnicas = [];
                $idsProcessados = [];
                
                foreach ($empresas as $empresa) {
                    // Evitar duplicatas baseado no ID
                    if (!in_array($empresa->id, $idsProcessados)) {
                        $empresasUnicas[] = [
                            'id' => $empresa->id,
                            'razao_social' => $empresa->razaoSocial ?? '',
                            'cnpj' => $empresa->cnpj ?? null,
                        ];
                        $idsProcessados[] = $empresa->id;
                    }
                }
                
                $empresasList = $empresasUnicas;
                
                if (method_exists($user, 'empresa_ativa_id') && $user->empresa_ativa_id) {
                    $empresaAtiva = $this->userRepository->buscarEmpresaAtiva($user->id);
                } else {
                    // Se não tem empresa ativa, usar primeira empresa
                    $empresaAtiva = !empty($empresas) ? $empresas[0] : null;
                }
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => method_exists($user, 'name') ? $user->name : null,
                'email' => $user->getAuthIdentifierName() === 'email' ? $user->email : null,
                'empresa_ativa_id' => method_exists($user, 'empresa_ativa_id') ? $user->empresa_ativa_id : null,
                'foto_perfil' => method_exists($user, 'foto_perfil') ? $user->foto_perfil : null,
                'empresas_list' => $empresasList, // Lista de empresas para o seletor
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'razao_social' => $tenant->razao_social,
                'cnpj' => $tenant->cnpj ?? null,
            ] : null,
            'empresa' => $empresaAtiva ? [
                'id' => $empresaAtiva->id,
                'razao_social' => $empresaAtiva->razaoSocial ?? '',
            ] : null,
        ];
    }
}

