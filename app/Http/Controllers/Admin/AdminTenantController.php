<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Events\EmpresaCriada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\TenantEmpresa;
use App\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;
use DomainException;

/**
 * Controller Admin para gerenciar empresas (tenants)
 * Usa DDD - apenas recebe request e devolve response
 */
class AdminTenantController extends Controller
{
    public function __construct(
        private CriarTenantUseCase $criarTenantUseCase,
        private TenantRepositoryInterface $tenantRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Listar todas as empresas (tenants)
     */
    public function index(Request $request)
    {
        try {
            // Preparar filtros
            $filters = [
                'status' => $request->status,
                'per_page' => $request->per_page ?? 15,
            ];

            // Se houver busca, usar campo search genérico
            if ($request->search || $request->razao_social || $request->cnpj || $request->email) {
                $filters['search'] = $request->search 
                    ?? $request->razao_social 
                    ?? $request->cnpj 
                    ?? $request->email;
            }

            $tenants = $this->tenantRepository->buscarComFiltros($filters);

            // Buscar modelos Eloquent para eager loading de relacionamentos
            // IMPORTANTE: Selecionar explicitamente criado_em e atualizado_em para garantir que sejam retornados
            $tenantIds = $tenants->pluck('id')->toArray();
            $tenantModels = Tenant::with(['planoAtual', 'assinaturaAtual'])
                ->whereIn('id', $tenantIds)
                ->select('*') // Selecionar todas as colunas (inclui timestamps)
                ->get()
                ->keyBy('id');

            // Converter entidades do domínio para array e usar ResponseBuilder padronizado
            return ApiResponse::paginated($tenants, function ($tenant) use ($tenantModels) {
                $tenantModel = $tenantModels->get($tenant->id);
                
                $data = [
                    'id' => $tenant->id,
                    'razao_social' => $tenant->razaoSocial,
                    'cnpj' => $tenant->cnpj,
                    'email' => $tenant->email,
                    'status' => $tenant->status,
                    'cidade' => $tenant->cidade,
                    'estado' => $tenant->estado,
                ];

                // 🔥 Adicionar timestamps (última atualização)
                // Tenant usa timestamps customizados: atualizado_em e criado_em
                if ($tenantModel) {
                    // Usar métodos do Eloquent para obter timestamps (funciona com timestamps customizados)
                    // O Eloquent automaticamente acessa os timestamps via getCreatedAt() e getUpdatedAt()
                    $criadoEm = $tenantModel->{$tenantModel->getCreatedAtColumn()}; // Acessa 'criado_em'
                    $atualizadoEm = $tenantModel->{$tenantModel->getUpdatedAtColumn()}; // Acessa 'atualizado_em'
                    
                    // Converter para ISO string se for Carbon/DateTime (cast automático do Eloquent)
                    if ($criadoEm instanceof \Carbon\Carbon) {
                        $data['created_at'] = $criadoEm->toISOString();
                    } elseif (is_string($criadoEm) && !empty($criadoEm)) {
                        $data['created_at'] = $criadoEm;
                    } else {
                        $data['created_at'] = null;
                    }
                    
                    if ($atualizadoEm instanceof \Carbon\Carbon) {
                        $data['updated_at'] = $atualizadoEm->toISOString();
                    } elseif (is_string($atualizadoEm) && !empty($atualizadoEm)) {
                        $data['updated_at'] = $atualizadoEm;
                    } else {
                        $data['updated_at'] = null;
                    }
                    
                    // Também retornar nos nomes customizados para compatibilidade
                    $data['criado_em'] = $data['created_at'];
                    $data['atualizado_em'] = $data['updated_at'];
                } else {
                    // Se não encontrou o modelo, definir como null
                    $data['created_at'] = null;
                    $data['updated_at'] = null;
                    $data['criado_em'] = null;
                    $data['atualizado_em'] = null;
                }

                // Adicionar informações de plano e assinatura se disponíveis
                if ($tenantModel) {
                    if ($tenantModel->planoAtual) {
                        $data['plano_atual'] = [
                            'id' => $tenantModel->planoAtual->id,
                            'nome' => $tenantModel->planoAtual->nome,
                            'preco_mensal' => $tenantModel->planoAtual->preco_mensal,
                            'preco_anual' => $tenantModel->planoAtual->preco_anual,
                        ];
                        $data['plano_atual_id'] = $tenantModel->plano_atual_id;
                    }
                    
                    if ($tenantModel->assinaturaAtual) {
                        $data['assinatura_atual'] = [
                            'id' => $tenantModel->assinaturaAtual->id,
                            'status' => $tenantModel->assinaturaAtual->status,
                            'valor_pago' => $tenantModel->assinaturaAtual->valor_pago,
                            'data_inicio' => $tenantModel->assinaturaAtual->data_inicio,
                            'data_fim' => $tenantModel->assinaturaAtual->data_fim,
                            'metodo_pagamento' => $tenantModel->assinaturaAtual->metodo_pagamento,
                            'transacao_id' => $tenantModel->assinaturaAtual->transacao_id,
                        ];
                        $data['assinatura_atual_id'] = $tenantModel->assinatura_atual_id;
                    }
                }

                return $data;
            });
        } catch (\Exception $e) {
            Log::error('Erro ao listar empresas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar empresas.'], 500);
        }
    }

    /**
     * Buscar empresa específica
     * 
     * 🔥 IMPORTANTE: Retorna dados do Tenant (empresa central) e também busca a Empresa
     * dentro do tenant para retornar o empresa_id correto (usado para filtrar usuários)
     */
    public function show(Tenant $tenant)
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);

            if (!$tenantDomain) {
                return response()->json(['message' => 'Empresa não encontrada.'], 404);
            }

            // 🔥 Buscar empresa dentro do tenant para obter o empresa_id correto
            // Isso é necessário porque os usuários são vinculados à Empresa (dentro do tenant),
            // não diretamente ao Tenant (empresa central)
            $empresaId = null;
            $empresaRazaoSocial = null;
            $empresaCnpj = null;
            
            try {
                // 1. Tentar buscar via mapeamento TenantEmpresa (mais rápido)
                $empresaIdMapeado = TenantEmpresa::findEmpresaIdByTenantId($tenant->id);
                
                if ($empresaIdMapeado) {
                    // Inicializar tenancy para buscar dados completos da empresa
                    $tenantModel = Tenant::find($tenant->id);
                    if ($tenantModel) {
                        Tenancy::initialize($tenantModel);
                        try {
                            $empresa = \App\Models\Empresa::find($empresaIdMapeado);
                            if ($empresa) {
                                $empresaId = $empresa->id;
                                $empresaRazaoSocial = $empresa->razao_social;
                                $empresaCnpj = $empresa->cnpj;
                            }
                        } finally {
                            Tenancy::end();
                        }
                    }
                } else {
                    // 2. Fallback: buscar primeira empresa do tenant (se não houver mapeamento)
                    $tenantModel = Tenant::find($tenant->id);
                    if ($tenantModel) {
                        Tenancy::initialize($tenantModel);
                        try {
                            $empresa = \App\Models\Empresa::first();
                            if ($empresa) {
                                $empresaId = $empresa->id;
                                $empresaRazaoSocial = $empresa->razao_social;
                                $empresaCnpj = $empresa->cnpj;
                                
                                // Criar mapeamento para próxima vez (cache)
                                try {
                                    TenantEmpresa::createOrUpdateMapping($tenant->id, $empresa->id);
                                } catch (\Exception $e) {
                                    Log::warning('Erro ao criar mapeamento TenantEmpresa', [
                                        'tenant_id' => $tenant->id,
                                        'empresa_id' => $empresa->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        } finally {
                            Tenancy::end();
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao buscar empresa dentro do tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continuar mesmo se não conseguir buscar empresa (empresa_id será null)
            }

            // Retornar como array padronizado com todos os campos
            $tenantData = [
                'id' => $tenantDomain->id,
                'razao_social' => $tenantDomain->razaoSocial,
                'cnpj' => $tenantDomain->cnpj,
                'email' => $tenantDomain->email,
                'status' => $tenantDomain->status,
                'endereco' => $tenantDomain->endereco,
                'cidade' => $tenantDomain->cidade,
                'estado' => $tenantDomain->estado,
                'cep' => $tenantDomain->cep,
                'telefones' => $tenantDomain->telefones,
                'emails_adicionais' => $tenantDomain->emailsAdicionais,
                'banco' => $tenantDomain->banco,
                'agencia' => $tenantDomain->agencia,
                'conta' => $tenantDomain->conta,
                'tipo_conta' => $tenantDomain->tipoConta,
                'pix' => $tenantDomain->pix,
                'representante_legal_nome' => $tenantDomain->representanteLegalNome,
                'representante_legal_cpf' => $tenantDomain->representanteLegalCpf,
                'representante_legal_cargo' => $tenantDomain->representanteLegalCargo,
                'logo' => $tenantDomain->logo,
                // 🔥 CRÍTICO: Retornar empresa_id da empresa dentro do tenant (usado para filtrar usuários)
                'empresa_id' => $empresaId, // ID da Empresa dentro do tenant (para filtrar usuários)
                'empresa_razao_social' => $empresaRazaoSocial, // Razão social da empresa dentro do tenant
                'empresa_cnpj' => $empresaCnpj, // CNPJ da empresa dentro do tenant
            ];

            return ApiResponse::item($tenantData);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar empresa', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar empresa.'], 500);
        }
    }

    /**
     * Criar nova empresa com banco de dados separado
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'razao_social' => 'required|string|max:255',
                'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj',
                'email' => 'nullable|email|max:255',
                'status' => 'nullable|string|in:ativa,inativa',
                'endereco' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'cep' => 'nullable|string|max:10',
                'telefones' => 'nullable|array',
                'emails_adicionais' => 'nullable|array',
                'banco' => 'nullable|string|max:255',
                'agencia' => 'nullable|string|max:255',
                'conta' => 'nullable|string|max:255',
                'tipo_conta' => 'nullable|string|in:corrente,poupanca',
                'pix' => 'nullable|string|max:255',
                'representante_legal_nome' => 'nullable|string|max:255',
                'representante_legal_cpf' => 'nullable|string|max:14',
                'representante_legal_cargo' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
                // Dados do administrador (opcional no admin)
                'admin_name' => 'nullable|string|max:255',
                'admin_email' => 'nullable|email|max:255',
                'admin_password' => 'nullable|string|min:8',
            ], [
                'razao_social.required' => 'A razão social da empresa é obrigatória.',
                'cnpj.unique' => 'Este CNPJ já está cadastrado no sistema.',
            ]);

            // Criar DTO - REMOVER dados de admin para não criar usuário automaticamente
            $validatedSemAdmin = $validated;
            unset($validatedSemAdmin['admin_name'], $validatedSemAdmin['admin_email'], $validatedSemAdmin['admin_password']);
            $dto = CriarTenantDTO::fromArray($validatedSemAdmin);

            // Executar Use Case - cria tenant, banco de dados separado e empresa (SEM criar usuário)
            $result = $this->criarTenantUseCase->executar($dto, requireAdmin: false);

            // Disparar evento de empresa criada para enviar email
            $this->eventDispatcher->dispatch(
                new EmpresaCriada(
                    tenantId: $result['tenant']->id,
                    razaoSocial: $result['tenant']->razaoSocial ?? $result['tenant']->razao_social,
                    cnpj: $result['tenant']->cnpj,
                    email: $result['tenant']->email,
                    empresaId: $result['empresa']->id,
                )
            );

            Log::info('AdminTenantController::store - Empresa criada e evento disparado', [
                'tenant_id' => $result['tenant']->id,
                'empresa_id' => $result['empresa']->id,
                'email' => $result['tenant']->email,
            ]);

            $message = 'Empresa criada com sucesso! Banco de dados separado criado. Agora você pode criar usuários para esta empresa.';

            return response()->json([
                'message' => $message,
                'success' => true,
                'data' => [
                    'tenant' => [
                        'id' => $result['tenant']->id,
                        'razao_social' => $result['tenant']->razaoSocial,
                        'cnpj' => $result['tenant']->cnpj,
                        'email' => $result['tenant']->email,
                        'status' => $result['tenant']->status,
                    ],
                    'empresa' => [
                        'id' => $result['empresa']->id,
                        'razao_social' => $result['empresa']->razaoSocial,
                    ],
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao criar empresa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao criar empresa. Tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }

    /**
     * Atualizar empresa
     */
    public function update(Request $request, Tenant $tenant)
    {
        try {
            $validated = $request->validate([
                'razao_social' => 'sometimes|required|string|max:255',
                'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj,' . $tenant->id,
                'email' => 'nullable|email|max:255',
                'status' => 'nullable|string|in:ativa,inativa',
                'endereco' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'cep' => 'nullable|string|max:10',
                'telefones' => 'nullable|array',
                'emails_adicionais' => 'nullable|array',
                'banco' => 'nullable|string|max:255',
                'agencia' => 'nullable|string|max:255',
                'conta' => 'nullable|string|max:255',
                'tipo_conta' => 'nullable|string|in:corrente,poupanca',
                'pix' => 'nullable|string|max:255',
                'representante_legal_nome' => 'nullable|string|max:255',
                'representante_legal_cpf' => 'nullable|string|max:14',
                'representante_legal_cargo' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
            ]);

            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);

            if (!$tenantDomain) {
                return response()->json(['message' => 'Empresa não encontrada.'], 404);
            }

            // Criar nova instância com dados atualizados
            $tenantAtualizado = new \App\Domain\Tenant\Entities\Tenant(
                id: $tenantDomain->id,
                razaoSocial: $validated['razao_social'] ?? $tenantDomain->razaoSocial,
                cnpj: $validated['cnpj'] ?? $tenantDomain->cnpj,
                email: $validated['email'] ?? $tenantDomain->email,
                status: $validated['status'] ?? $tenantDomain->status,
                endereco: $validated['endereco'] ?? $tenantDomain->endereco,
                cidade: $validated['cidade'] ?? $tenantDomain->cidade,
                estado: $validated['estado'] ?? $tenantDomain->estado,
                cep: $validated['cep'] ?? $tenantDomain->cep,
                telefones: $validated['telefones'] ?? $tenantDomain->telefones,
                emailsAdicionais: $validated['emails_adicionais'] ?? $tenantDomain->emailsAdicionais,
                banco: $validated['banco'] ?? $tenantDomain->banco,
                agencia: $validated['agencia'] ?? $tenantDomain->agencia,
                conta: $validated['conta'] ?? $tenantDomain->conta,
                tipoConta: $validated['tipo_conta'] ?? $tenantDomain->tipoConta,
                pix: $validated['pix'] ?? $tenantDomain->pix,
                representanteLegalNome: $validated['representante_legal_nome'] ?? $tenantDomain->representanteLegalNome,
                representanteLegalCpf: $validated['representante_legal_cpf'] ?? $tenantDomain->representanteLegalCpf,
                representanteLegalCargo: $validated['representante_legal_cargo'] ?? $tenantDomain->representanteLegalCargo,
                logo: $validated['logo'] ?? $tenantDomain->logo,
            );

            $tenantAtualizado = $this->tenantRepository->atualizar($tenantAtualizado);

            return response()->json([
                'message' => 'Empresa atualizada com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $tenantAtualizado->id,
                    'razao_social' => $tenantAtualizado->razaoSocial,
                    'cnpj' => $tenantAtualizado->cnpj,
                    'status' => $tenantAtualizado->status,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar empresa', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar empresa.'], 500);
        }
    }

    /**
     * Inativar empresa (soft delete)
     */
    public function destroy(Tenant $tenant)
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);

            if (!$tenantDomain) {
                return response()->json(['message' => 'Empresa não encontrada.'], 404);
            }

            // Atualizar status para inativa em vez de deletar
            $tenantInativo = new \App\Domain\Tenant\Entities\Tenant(
                id: $tenantDomain->id,
                razaoSocial: $tenantDomain->razaoSocial,
                cnpj: $tenantDomain->cnpj,
                email: $tenantDomain->email,
                status: 'inativa',
                endereco: $tenantDomain->endereco,
                cidade: $tenantDomain->cidade,
                estado: $tenantDomain->estado,
                cep: $tenantDomain->cep,
                telefones: $tenantDomain->telefones,
                emailsAdicionais: $tenantDomain->emailsAdicionais,
                banco: $tenantDomain->banco,
                agencia: $tenantDomain->agencia,
                conta: $tenantDomain->conta,
                tipoConta: $tenantDomain->tipoConta,
                pix: $tenantDomain->pix,
                representanteLegalNome: $tenantDomain->representanteLegalNome,
                representanteLegalCpf: $tenantDomain->representanteLegalCpf,
                representanteLegalCargo: $tenantDomain->representanteLegalCargo,
                logo: $tenantDomain->logo,
            );

            $this->tenantRepository->atualizar($tenantInativo);

            return response()->json([
                'message' => 'Empresa inativada com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao inativar empresa', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao inativar empresa.'], 500);
        }
    }

    /**
     * Reativar empresa
     */
    public function reactivate(Tenant $tenant)
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);

            if (!$tenantDomain) {
                return response()->json(['message' => 'Empresa não encontrada.'], 404);
            }

            $tenantAtivo = new \App\Domain\Tenant\Entities\Tenant(
                id: $tenantDomain->id,
                razaoSocial: $tenantDomain->razaoSocial,
                cnpj: $tenantDomain->cnpj,
                email: $tenantDomain->email,
                status: 'ativa',
                endereco: $tenantDomain->endereco,
                cidade: $tenantDomain->cidade,
                estado: $tenantDomain->estado,
                cep: $tenantDomain->cep,
                telefones: $tenantDomain->telefones,
                emailsAdicionais: $tenantDomain->emailsAdicionais,
                banco: $tenantDomain->banco,
                agencia: $tenantDomain->agencia,
                conta: $tenantDomain->conta,
                tipoConta: $tenantDomain->tipoConta,
                pix: $tenantDomain->pix,
                representanteLegalNome: $tenantDomain->representanteLegalNome,
                representanteLegalCpf: $tenantDomain->representanteLegalCpf,
                representanteLegalCargo: $tenantDomain->representanteLegalCargo,
                logo: $tenantDomain->logo,
            );

            $this->tenantRepository->atualizar($tenantAtivo);

            return response()->json([
                'message' => 'Empresa reativada com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao reativar empresa', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao reativar empresa.'], 500);
        }
    }
}

