<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\UseCases\AtualizarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Application\Auth\Presenters\UserPresenter;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use DomainException;

/**
 * Controller Admin para gerenciar usuários das empresas
 * Controller FINO - apenas recebe request e devolve response
 * Toda lógica está nos Use Cases
 */
class AdminUserController extends Controller
{
    public function __construct(
        private CriarUsuarioUseCase $criarUsuarioUseCase,
        private AtualizarUsuarioUseCase $atualizarUsuarioUseCase,
        private UserRepositoryInterface $userRepository,
        private UserReadRepositoryInterface $userReadRepository,
    ) {}

    /**
     * Listar usuários de uma empresa (tenant)
     * Middleware InitializeTenant cuida do contexto
     */
    public function index(Request $request, Tenant $tenant)
    {
        try {
            $filtros = [
                'search' => $request->search,
                'per_page' => $request->per_page ?? 15,
            ];

            $users = $this->userRepository->buscarComFiltros($filtros);

            // Converter entidades do domínio para array
            $data = $users->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                    'empresa_ativa_id' => $user->empresaAtivaId,
                ];
            });

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar usuários', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar usuários.'], 500);
        }
    }

    /**
     * Buscar usuário específico
     */
    public function show(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $user = $this->userRepository->buscarPorId($userId);

            if (!$user) {
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }

            // Buscar modelo Eloquent para roles/empresas (pode ser melhorado com Domain Service)
            $userModel = \App\Modules\Auth\Models\User::with(['empresas', 'roles'])->find($userId);

            return response()->json([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                    'empresa_ativa_id' => $user->empresaAtivaId,
                    'roles' => $userModel?->roles->pluck('name') ?? [],
                    'empresas' => $userModel?->empresas->map(fn($e) => [
                        'id' => $e->id,
                        'razao_social' => $e->razao_social,
                    ]) ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar usuário.'], 500);
        }
    }

    /**
     * Criar novo usuário na empresa
     * Use Case cuida de toda a lógica
     */
    public function store(Request $request, Tenant $tenant)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => ['required', 'string', 'min:8', new \App\Rules\StrongPassword()],
                'empresa_id' => 'required|integer',
                'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
            ], [
                'name.required' => 'O nome é obrigatório.',
                'email.required' => 'O e-mail é obrigatório.',
                'password.required' => 'A senha é obrigatória.',
                'empresa_id.required' => 'A empresa é obrigatória.',
                'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
            ]);

            // Criar DTO
            $dto = CriarUsuarioDTO::fromRequest($request, $tenant->id);

            // Executar Use Case (toda a lógica está aqui)
            $user = $this->criarUsuarioUseCase->executar($dto);

            return response()->json([
                'message' => 'Usuário criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                    'role' => $validated['role'] ?? 'Usuário',
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['email' => [$e->getMessage()]],
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao criar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao criar usuário.'], 500);
        }
    }

    /**
     * Atualizar usuário
     * Use Case cuida de toda a lógica
     */
    public function update(Request $request, Tenant $tenant, int $userId)
    {
        try {
            // Normalizar password: string vazia vira null
            $data = $request->all();
            if (isset($data['password']) && trim($data['password']) === '') {
                $data['password'] = null;
            }
            $request->merge($data);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255',
                'password' => ['nullable', 'string', 'min:8', new \App\Rules\StrongPassword()],
                'empresa_id' => 'sometimes|required|integer',
                'role' => 'nullable|string|in:Administrador,Operacional,Financeiro,Consulta',
            ], [
                'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
            ]);

            // Criar DTO
            $dto = AtualizarUsuarioDTO::fromRequest($request, $tenant->id, $userId);

            // Executar Use Case (toda a lógica está aqui)
            $user = $this->atualizarUsuarioUseCase->executar($dto);

            return response()->json([
                'message' => 'Usuário atualizado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['email' => [$e->getMessage()]],
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar usuário.'], 500);
        }
    }

    /**
     * Excluir usuário (soft delete)
     */
    public function destroy(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $user = $this->userRepository->buscarPorId($userId);

            if (!$user) {
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }

            $this->userRepository->deletar($userId);

            return response()->json([
                'message' => 'Usuário excluído com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao excluir usuário.'], 500);
        }
    }

    /**
     * Reativar usuário
     */
    public function reactivate(Request $request, Tenant $tenant, int $userId)
    {
        try {
            $this->userRepository->reativar($userId);

            return response()->json([
                'message' => 'Usuário reativado com sucesso!',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao reativar usuário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao reativar usuário.'], 500);
        }
    }

    /**
     * Listar empresas disponíveis para o usuário
     */
    public function empresas(Request $request, Tenant $tenant)
    {
        try {
            $empresas = \Illuminate\Support\Facades\DB::table('empresas')
                ->select('id', 'razao_social', 'cnpj', 'status')
                ->where('status', 'ativa')
                ->orderBy('razao_social')
                ->get();

            return response()->json([
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar empresas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar empresas.'], 500);
        }
    }
}
