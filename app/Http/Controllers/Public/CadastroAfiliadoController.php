<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Afiliado\UseCases\CriarAfiliadoUseCase;
use App\Application\Afiliado\DTOs\CriarAfiliadoDTO;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

/**
 * Controller para cadastro público de afiliados
 * 
 * Permite que pessoas se cadastrem como afiliados sem precisar de autenticação admin
 */
final class CadastroAfiliadoController extends Controller
{
    public function __construct(
        private readonly CriarAfiliadoUseCase $criarAfiliadoUseCase,
    ) {}

    /**
     * Cadastrar novo afiliado (público)
     * 
     * POST /api/v1/afiliados/cadastro-publico
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validação dos dados
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'documento' => 'required|string|max:20',
                'tipo_documento' => 'required|in:cpf,cnpj',
                'email' => 'required|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'endereco' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'cep' => 'nullable|string|max:10',
            ]);

            Log::info('CadastroAfiliadoController::store - Iniciando cadastro público de afiliado', [
                'email' => $validated['email'],
                'nome' => $validated['nome'],
            ]);

            // Criar DTO
            $dto = CriarAfiliadoDTO::fromArray($validated);

            // Executar use case
            $afiliado = $this->criarAfiliadoUseCase->executar($dto);

            // Construir link de afiliado
            $baseUrl = env('FRONTEND_URL', config('app.frontend_url', 'https://addsimp.com'));
            // Remover barra final se houver
            $baseUrl = rtrim($baseUrl, '/');
            $linkAfiliado = "{$baseUrl}/?ref={$afiliado->codigo}";

            Log::info('CadastroAfiliadoController::store - Afiliado criado com sucesso', [
                'afiliado_id' => $afiliado->id,
                'codigo' => $afiliado->codigo,
                'link' => $linkAfiliado,
            ]);

            return response()->json([
                'message' => 'Cadastro realizado com sucesso! Seu link de afiliado foi gerado.',
                'success' => true,
                'data' => [
                    'afiliado' => [
                        'id' => $afiliado->id,
                        'nome' => $afiliado->nome,
                        'email' => $afiliado->email,
                        'codigo' => $afiliado->codigo,
                        'link_afiliado' => $linkAfiliado,
                    ],
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            Log::warning('CadastroAfiliadoController::store - Erro de domínio', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
            ], 422);
        } catch (QueryException $e) {
            // Capturar erros de constraint única do banco de dados
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            Log::warning('CadastroAfiliadoController::store - Erro de banco de dados', [
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'sql_state' => $e->errorInfo[0] ?? null,
            ]);

            // Verificar se é violação de constraint única
            if ($errorCode === '23505' || str_contains($errorMessage, 'duplicate key') || str_contains($errorMessage, 'unique constraint')) {
                // Tentar identificar qual campo causou o erro
                if (str_contains($errorMessage, 'documento') || str_contains($errorMessage, 'afiliados_documento_unique')) {
                    return response()->json([
                        'message' => 'Já existe um afiliado cadastrado com este documento (CPF/CNPJ).',
                        'errors' => [
                            'documento' => ['Este documento já está cadastrado no sistema.'],
                        ],
                        'success' => false,
                    ], 422);
                }
                
                if (str_contains($errorMessage, 'email') || str_contains($errorMessage, 'afiliados_email_unique')) {
                    return response()->json([
                        'message' => 'Já existe um afiliado cadastrado com este e-mail.',
                        'errors' => [
                            'email' => ['Este e-mail já está cadastrado no sistema.'],
                        ],
                        'success' => false,
                    ], 422);
                }
                
                if (str_contains($errorMessage, 'codigo') || str_contains($errorMessage, 'afiliados_codigo_unique')) {
                    return response()->json([
                        'message' => 'Já existe um afiliado com este código. Tente novamente.',
                        'success' => false,
                    ], 422);
                }
                
                // Erro genérico de duplicação
                return response()->json([
                    'message' => 'Já existe um cadastro com estes dados. Verifique se você já não está cadastrado.',
                    'success' => false,
                ], 422);
            }

            // Outros erros de banco de dados
            Log::error('CadastroAfiliadoController::store - Erro de banco de dados não tratado', [
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao realizar cadastro. Tente novamente mais tarde.',
                'success' => false,
            ], 500);
        } catch (\Exception $e) {
            Log::error('CadastroAfiliadoController::store - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao realizar cadastro. Tente novamente mais tarde.',
                'success' => false,
            ], 500);
        }
    }
}

