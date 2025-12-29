<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\DocumentoHabilitacao\UseCases\CriarDocumentoHabilitacaoUseCase;
use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class DocumentoHabilitacaoController extends Controller
{
    public function __construct(
        private CriarDocumentoHabilitacaoUseCase $criarDocumentoUseCase,
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'tipo' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:255',
                'identificacao' => 'nullable|string|max:255',
                'data_emissao' => 'nullable|date',
                'data_validade' => 'nullable|date',
                'arquivo' => 'nullable|string|max:500',
                'ativo' => 'nullable|boolean',
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
            ]);

            $dto = CriarDocumentoHabilitacaoDTO::fromArray($validated);
            $documento = $this->criarDocumentoUseCase->executar($dto);

            return response()->json([
                'message' => 'Documento de habilitação criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $documento->id,
                    'tipo' => $documento->tipo,
                    'numero' => $documento->numero,
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
            Log::error('Erro ao criar documento de habilitação', [
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
            $filtros = $request->only(['empresa_id', 'tipo', 'per_page']);
            $documentos = $this->documentoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $documentos->items(),
                'pagination' => [
                    'current_page' => $documentos->currentPage(),
                    'per_page' => $documentos->perPage(),
                    'total' => $documentos->total(),
                    'last_page' => $documentos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar documentos de habilitação', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar documentos de habilitação.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $documento = $this->documentoRepository->buscarPorId($id);

            if (!$documento) {
                return response()->json(['message' => 'Documento de habilitação não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $documento->id,
                'tipo' => $documento->tipo,
                'numero' => $documento->numero,
                'vencido' => $documento->estaVencido(),
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar documento de habilitação', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar documento de habilitação.'], 500);
        }
    }
}

