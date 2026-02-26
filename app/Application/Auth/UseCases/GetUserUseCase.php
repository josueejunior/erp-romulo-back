<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Services\AdminTenancyRunner;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Use Case: Obter Dados do Usuário Autenticado
 * 
 * 🔥 ARQUITETURA LIMPA: Usa TenantRepository e AdminTenancyRunner
 */
class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
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
        $tenantModel = null;
        $tenantDomain = null;
        $tenantId = null;
        
        // Prioridade 1: Usar tenant já inicializado pelo middleware (mais confiável)
        if (tenancy()->initialized && tenancy()->tenant) {
            $tenantModel = tenancy()->tenant;
            $tenantId = $tenantModel->id;
            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            } else {
                // Prioridade 2: Tentar obter do header (se middleware não inicializou)
                $request = request();
                if ($request && $request->header('X-Tenant-ID')) {
                    $tenantId = (int) $request->header('X-Tenant-ID');
                    $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
                    if ($tenantDomain) {
                        $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantId);
                    }
                } else {
                    // 🔥 JWT STATELESS: Prioridade 3: Obter do payload JWT
                    $request = request();
                    if ($request && $request->attributes->has('auth')) {
                        $payload = $request->attributes->get('auth');
                        $tenantId = $payload['tenant_id'] ?? null;
                        
                        if ($tenantId) {
                            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
                            if ($tenantDomain) {
                                $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantId);
                            }
                        }
                    }
                }
            }

        // Buscar empresa ativa e lista de empresas
        $empresaAtiva = null;
        $empresasList = [];
        if ($tenantDomain) {
            // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
            $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($user, &$empresaAtiva, &$empresasList) {
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
            });
        }

        // Buscar modelo completo do usuário para garantir que temos todos os dados
        $userModel = $this->userRepository->buscarModeloPorId($user->id);
        
        // Montar dados completos do tenant (usando modelo para ter todos os campos expostos no frontend)
        $tenantResponse = null;
        if ($tenantDomain && $tenantModel) {
            // Usar toArray() do modelo para garantir que todas as colunas customizadas estejam presentes
            $tenantArray = $tenantModel->toArray();

            // Garantir aliases de compatibilidade esperados pelo frontend
            if (!array_key_exists('razao_social', $tenantArray)) {
                $tenantArray['razao_social'] = $tenantModel->razao_social ?? $tenantDomain->razaoSocial ?? '';
            }
            if (!array_key_exists('cnpj', $tenantArray)) {
                $tenantArray['cnpj'] = $tenantModel->cnpj ?? $tenantDomain->cnpj ?? null;
            }
            if (!array_key_exists('uf', $tenantArray)) {
                $tenantArray['uf'] = $tenantModel->estado ?? null;
            }
            if (!array_key_exists('telefone', $tenantArray)) {
                // Alias simples: o frontend espera um campo "telefone" além do array "telefones"
                $tenantArray['telefone'] = $tenantModel->telefones ?? null;
            }

            $tenantResponse = $tenantArray;
        } elseif ($tenantDomain) {
            // Fallback: usar apenas dados do domain se não tiver modelo
            $tenantResponse = [
                'id' => $tenantDomain->id,
                'razao_social' => $tenantDomain->razaoSocial,
                'cnpj' => $tenantDomain->cnpj ?? null,
            ];
        }
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $userModel?->name ?? $user->name ?? null,
                'email' => $userModel?->email ?? $user->email ?? null,
                'empresa_ativa_id' => $userModel?->empresa_ativa_id ?? $user->empresa_ativa_id ?? null,
                'foto_perfil' => $userModel?->foto_perfil ?? $user->foto_perfil ?? null,
                'empresas_list' => $empresasList, // Lista de empresas para o seletor
            ],
            'tenant' => $tenantResponse,
            'empresa' => $empresaAtiva ? [
                'id' => $empresaAtiva->id,
                'razao_social' => $empresaAtiva->razaoSocial ?? '',
            ] : null,
        ];
    }
}

