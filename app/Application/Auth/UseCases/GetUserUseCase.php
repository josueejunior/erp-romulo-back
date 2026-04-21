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
        
        // Montar dados completos do tenant (usando modelo se disponível para ter todos os campos)
        $tenantResponse = null;
        if ($tenantDomain && $tenantModel) {
            $tenantData = is_array($tenantModel->data) ? $tenantModel->data : [];
            $tenantAttrs = method_exists($tenantModel, 'getAttributes') ? $tenantModel->getAttributes() : [];
            $tenantTelefones = $tenantModel->telefones ?? [];
            $telefonePrincipal = null;
            if (is_array($tenantTelefones) && !empty($tenantTelefones)) {
                $primeiroTelefone = $tenantTelefones[0];
                $telefonePrincipal = is_array($primeiroTelefone)
                    ? ($primeiroTelefone['numero'] ?? null)
                    : $primeiroTelefone;
            }

            $tenantResponse = [
                'id' => $tenantModel->id,
                'razao_social' => $tenantModel->razao_social ?? $tenantDomain->razaoSocial ?? '',
                'cnpj' => $tenantModel->cnpj ?? $tenantDomain->cnpj ?? null,
                'email' => $tenantModel->email ?? null,
                'endereco' => $tenantModel->endereco ?? null,
                'cidade' => $tenantModel->cidade ?? null,
                'estado' => $tenantModel->estado ?? null,
                'uf' => $tenantModel->estado ?? null, // Alias para compatibilidade
                'cep' => $tenantModel->cep ?? null,
                'telefones' => $tenantTelefones,
                'telefone' => $telefonePrincipal, // Alias para compatibilidade
                'emails_adicionais' => $tenantModel->emails_adicionais ?? null,
                'banco' => $tenantData['banco'] ?? ($tenantAttrs['banco'] ?? null),
                'agencia' => $tenantData['agencia'] ?? ($tenantAttrs['agencia'] ?? null),
                'conta' => $tenantData['conta'] ?? ($tenantAttrs['conta'] ?? null),
                'tipo_conta' => $tenantData['tipo_conta'] ?? ($tenantAttrs['tipo_conta'] ?? null),
                'pix' => $tenantData['pix'] ?? ($tenantAttrs['pix'] ?? null),
                'representante_legal_nome' => $tenantData['representante_legal_nome'] ?? ($tenantAttrs['representante_legal_nome'] ?? null),
                'representante_legal_cpf' => $tenantData['representante_legal_cpf'] ?? ($tenantAttrs['representante_legal_cpf'] ?? null),
                'representante_legal_cargo' => $tenantData['representante_legal_cargo'] ?? ($tenantAttrs['representante_legal_cargo'] ?? null),
                'logo' => $tenantModel->logo ?? null,
                'numero' => $tenantData['numero'] ?? null,
                'bairro' => $tenantData['bairro'] ?? null,
                'complemento' => $tenantData['complemento'] ?? null,
                'email_financeiro' => $tenantData['email_financeiro'] ?? null,
                'email_licitacao' => $tenantData['email_licitacao'] ?? null,
                'whatsapp' => $tenantData['whatsapp'] ?? null,
                'telefone_fixo' => $tenantData['telefone_fixo'] ?? null,
                'site' => $tenantData['site'] ?? null,
                'inscricao_estadual' => $tenantData['inscricao_estadual'] ?? null,
                'inscricao_municipal' => $tenantData['inscricao_municipal'] ?? null,
                'cnae_principal' => $tenantData['cnae_principal'] ?? null,
                'regime_tributario' => $tenantData['regime_tributario'] ?? null,
                'dados_complementares' => $tenantData['dados_complementares'] ?? null,
                'data' => $tenantData,
            ];
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

