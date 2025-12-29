<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Controller fino - apenas recebe request e devolve response
 * NÃO tem regra de negócio
 */
class TenantController extends Controller
{
    public function __construct(
        private CriarTenantUseCase $criarTenantUseCase
    ) {}

    /**
     * Criar um novo tenant (empresa) com usuário administrador
     */
    public function store(Request $request)
    {
        try {
            // Validação básica (apenas formato dos dados)
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
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255',
                'admin_password' => ['required', 'string', 'min:8', new \App\Rules\StrongPassword()],
            ], [
                'razao_social.required' => 'A razão social da empresa é obrigatória.',
                'cnpj.unique' => 'Este CNPJ já está cadastrado no sistema.',
                'admin_name.required' => 'O nome do administrador é obrigatório.',
                'admin_email.required' => 'O e-mail do administrador é obrigatório.',
                'admin_password.required' => 'A senha do administrador é obrigatória.',
            ]);

            // Criar DTO
            $dto = CriarTenantDTO::fromArray($validated);

            // Executar Use Case (aqui está a lógica)
            $result = $this->criarTenantUseCase->executar($dto, requireAdmin: true);

            $message = $result['admin_user'] 
                ? 'Empresa e usuário administrador criados com sucesso!'
                : 'Empresa criada com sucesso!';

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
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao criar tenant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $e->getMessage() ?? 'Erro ao processar a solicitação. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }

    /**
     * Listar tenants
     */
    public function index(Request $request)
    {
        // TODO: Implementar Use Case de listagem
        return response()->json(['message' => 'Em implementação']);
    }

    /**
     * Mostrar tenant específico
     */
    public function show($id)
    {
        // TODO: Implementar Use Case de busca
        return response()->json(['message' => 'Em implementação']);
    }
}

