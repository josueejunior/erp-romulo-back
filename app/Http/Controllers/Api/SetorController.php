<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Setor\UseCases\CriarSetorUseCase;
use App\Application\Setor\DTOs\CriarSetorDTO;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class SetorController extends Controller
{
    public function __construct(
        private CriarSetorUseCase $criarSetorUseCase,
        private SetorRepositoryInterface $setorRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'orgao_id' => 'nullable|integer|exists:orgaos,id',
                'nome' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
                'nome.required' => 'O nome do setor é obrigatório.',
            ]);

            $dto = CriarSetorDTO::fromArray($validated);
            $setor = $this->criarSetorUseCase->executar($dto);

            return response()->json([
                'message' => 'Setor criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $setor->id,
                    'nome' => $setor->nome,
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
            Log::error('Erro ao criar setor', [
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
            $filtros = $request->only(['empresa_id', 'orgao_id', 'per_page']);
            $setors = $this->setorRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $setors->items(),
                'pagination' => [
                    'current_page' => $setors->currentPage(),
                    'per_page' => $setors->perPage(),
                    'total' => $setors->total(),
                    'last_page' => $setors->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar setores', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar setores.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $setor = $this->setorRepository->buscarPorId($id);

            if (!$setor) {
                return response()->json(['message' => 'Setor não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $setor->id,
                'nome' => $setor->nome,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar setor', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar setor.'], 500);
        }
    }
}

