<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Fornecedor\UseCases\CriarFornecedorUseCase;
use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class FornecedorController extends Controller
{
    public function __construct(
        private CriarFornecedorUseCase $criarFornecedorUseCase,
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'razao_social' => 'required|string|max:255',
                'cnpj' => 'nullable|string|max:18',
                'nome_fantasia' => 'nullable|string|max:255',
                'cep' => 'nullable|string|max:10',
                'logradouro' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:20',
                'bairro' => 'nullable|string|max:255',
                'complemento' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'emails' => 'nullable|array',
                'telefones' => 'nullable|array',
                'contato' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
                'is_transportadora' => 'nullable|boolean',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'razao_social.required' => 'A razão social é obrigatória.',
            ]);

            $dto = CriarFornecedorDTO::fromArray($validated);
            $fornecedor = $this->criarFornecedorUseCase->executar($dto);

            return response()->json([
                'message' => 'Fornecedor criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $fornecedor->id,
                    'razao_social' => $fornecedor->razaoSocial,
                    'cnpj' => $fornecedor->cnpj,
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
            Log::error('Erro ao criar fornecedor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar a solicitação.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $filtros = $request->only(['empresa_id', 'search', 'per_page']);
            $fornecedores = $this->fornecedorRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $fornecedores->items(),
                'pagination' => [
                    'current_page' => $fornecedores->currentPage(),
                    'per_page' => $fornecedores->perPage(),
                    'total' => $fornecedores->total(),
                    'last_page' => $fornecedores->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar fornecedores', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar fornecedores.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $fornecedor = $this->fornecedorRepository->buscarPorId($id);

            if (!$fornecedor) {
                return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $fornecedor->id,
                'razao_social' => $fornecedor->razaoSocial,
                'cnpj' => $fornecedor->cnpj,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar fornecedor', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar fornecedor.'], 500);
        }
    }
}

