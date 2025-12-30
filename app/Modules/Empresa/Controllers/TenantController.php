<?php

namespace App\Modules\Empresa\Controllers;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Http\Requests\Tenant\TenantCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Controller fino - apenas recebe request e devolve response
 * NÃO tem regra de negócio
 * 
 * Organizado por módulo seguindo Arquitetura Hexagonal
 */
class TenantController extends Controller
{
    public function __construct(
        private CriarTenantUseCase $criarTenantUseCase
    ) {}

    /**
     * Criar um novo tenant (empresa) com usuário administrador
     * Usa Form Request para validação
     */
    public function store(TenantCreateRequest $request)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

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
    public function list(Request $request)
    {
        // TODO: Implementar Use Case de listagem
        return response()->json(['message' => 'Em implementação']);
    }

    /**
     * Mostrar tenant específico
     */
    public function get(Request $request, $id)
    {
        // TODO: Implementar Use Case de busca
        return response()->json(['message' => 'Em implementação']);
    }
}

