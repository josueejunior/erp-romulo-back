<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Application\Auth\UseCases\LoginAdminUseCase;
use App\Application\Auth\UseCases\LogoutAdminUseCase;
use App\Application\Auth\UseCases\ObterDadosAdminUseCase;
use App\Application\Auth\DTOs\LoginAdminDTO;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 DDD: Controller Admin para autenticação
 * 
 * Controller FINO - apenas recebe request e devolve response
 * Toda lógica está nos UseCases, Domain Services e FormRequests
 * 
 * Responsabilidades:
 * - Receber request HTTP
 * - Validar entrada (via FormRequest)
 * - Chamar UseCase apropriado
 * - Retornar response padronizado (ApiResponse)
 */
class AdminAuthController extends Controller
{
    public function __construct(
        private readonly LoginAdminUseCase $loginAdminUseCase,
        private readonly LogoutAdminUseCase $logoutAdminUseCase,
        private readonly ObterDadosAdminUseCase $obterDadosAdminUseCase,
    ) {}

    /**
     * Login do administrador
     * 🔥 DDD: Controller fino - validação via FormRequest, delega para UseCase
     */
    public function login(LoginAdminRequest $request): JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Criar DTO
            $dto = LoginAdminDTO::fromRequest($validated);

            // Executar Use Case
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
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao fazer login.', 500);
        }
    }

    /**
     * Logout do administrador
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();

            // Executar Use Case
            $this->logoutAdminUseCase->executar($admin);

            return ApiResponse::success('Logout realizado com sucesso!');
        } catch (\Exception $e) {
            Log::error('AdminAuthController::logout - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao fazer logout.', 500);
        }
    }

    /**
     * Obter dados do administrador autenticado
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();

            // Executar Use Case
            $data = $this->obterDadosAdminUseCase->executar($admin);

            return ApiResponse::item($data);
        } catch (\Exception $e) {
            Log::error('AdminAuthController::me - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao obter dados.', 500);
        }
    }
}




