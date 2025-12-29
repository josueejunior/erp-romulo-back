<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
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

            // Converter entidades do domínio para array
            $data = $tenants->getCollection()->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'razao_social' => $tenant->razaoSocial,
                    'cnpj' => $tenant->cnpj,
                    'email' => $tenant->email,
                    'status' => $tenant->status,
                    'cidade' => $tenant->cidade,
                    'estado' => $tenant->estado,
                ];
            });

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'current_page' => $tenants->currentPage(),
                    'per_page' => $tenants->perPage(),
                    'total' => $tenants->total(),
                    'last_page' => $tenants->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar empresas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar empresas.'], 500);
        }
    }

    /**
     * Buscar empresa específica
     */
    public function show(Tenant $tenant)
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenant->id);

            if (!$tenantDomain) {
                return response()->json(['message' => 'Empresa não encontrada.'], 404);
            }

            // Retornar objeto único (show retorna um item, index retorna array)
            return response()->json([
                'data' => [
                    'id' => $tenantDomain->id,
                    'razao_social' => $tenantDomain->razaoSocial,
                    'cnpj' => $tenantDomain->cnpj,
                    'email' => $tenantDomain->email,
                    'status' => $tenantDomain->status,
                    'cidade' => $tenantDomain->cidade,
                    'estado' => $tenantDomain->estado,
                ],
            ]);
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

            // Criar DTO
            $dto = CriarTenantDTO::fromArray($validated);

            // Executar Use Case - cria tenant, banco de dados separado, empresa e admin (se fornecido)
            $result = $this->criarTenantUseCase->executar($dto, requireAdmin: false);

            $message = $result['admin_user'] 
                ? 'Empresa criada com sucesso! Banco de dados separado criado e usuário administrador configurado.'
                : 'Empresa criada com sucesso! Banco de dados separado criado.';

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
                    'admin_user' => $result['admin_user'] ? [
                        'id' => $result['admin_user']->id,
                        'name' => $result['admin_user']->name,
                        'email' => $result['admin_user']->email,
                    ] : null,
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

