<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Application\Auth\UseCases\LoginAdminUseCase;
use App\Application\Auth\UseCases\LogoutAdminUseCase;
use App\Application\Auth\UseCases\ObterDadosAdminUseCase;
use App\Application\Auth\UseCases\AtualizarPerfilAdminUseCase;
use App\Application\Auth\UseCases\AlterarSenhaAdminUseCase;
use App\Application\Auth\DTOs\LoginAdminDTO;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 DDD: Controller Admin para autenticação
 */
class AdminAuthController extends Controller
{
    public function __construct(
        private readonly LoginAdminUseCase $loginAdminUseCase,
        private readonly LogoutAdminUseCase $logoutAdminUseCase,
        private readonly ObterDadosAdminUseCase $obterDadosAdminUseCase,
        private readonly AtualizarPerfilAdminUseCase $atualizarPerfilAdminUseCase,
        private readonly AlterarSenhaAdminUseCase $alterarSenhaAdminUseCase,
    ) {}

    /**
     * Login do administrador
     */
    public function login(LoginAdminRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $dto = LoginAdminDTO::fromRequest($validated);
            $data = $this->loginAdminUseCase->executar($dto);

            return ApiResponse::success(
                'Login realizado com sucesso!',
                $data
            );
        } catch (DomainException $e) {
            return ApiResponse::error(
                $e->getMessage(),
                $e->getCode() ?: 401,
                null,
                ['email' => [$e->getMessage()]]
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Dados inválidos.',
                422,
                null,
                $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('AdminAuthController::login - Erro inesperado', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Erro ao fazer login.', 500);
        }
    }

    /**
     * Logout do administrador
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $this->logoutAdminUseCase->executar($admin);
            return ApiResponse::success('Logout realizado com sucesso!');
        } catch (\Exception $e) {
            Log::error('AdminAuthController::logout - Erro', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Erro ao fazer logout.', 500);
        }
    }

    /**
     * Obter dados do administrador autenticado
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $data = $this->obterDadosAdminUseCase->executar($admin);
            return ApiResponse::single($data);
        } catch (\Exception $e) {
            Log::error('AdminAuthController::me - Erro', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Erro ao obter dados.', 500);
        }
    }

    /**
     * Atualizar perfil do administrador
     */
    public function atualizarPerfil(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
            ]);

            $data = $this->atualizarPerfilAdminUseCase->executar($admin, $validated);

            return ApiResponse::success('Perfil updated com sucesso!', $data);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (ValidationException $e) {
            return ApiResponse::error('Dados inválidos.', 422, null, $e->errors());
        } catch (\Exception $e) {
            Log::error('AdminAuthController::atualizarPerfil - Erro', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Erro ao atualizar perfil.', 500);
        }
    }

    /**
     * Alterar senha do administrador
     */
    public function alterarSenha(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            $validated = $request->validate([
                'senha_atual' => 'required|string',
                'senha_nova' => 'required|string|min:6',
            ]);

            $this->alterarSenhaAdminUseCase->executar(
                $admin, 
                $validated['senha_atual'], 
                $validated['senha_nova']
            );

            return ApiResponse::success('Senha alterada com sucesso!');
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (ValidationException $e) {
            return ApiResponse::error('Dados inválidos.', 422, null, $e->errors());
        } catch (\Exception $e) {
            Log::error('AdminAuthController::alterarSenha - Erro', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Erro ao alterar senha.', 500);
        }
    }
}
