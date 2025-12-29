<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Orgao\UseCases\CriarOrgaoUseCase;
use App\Application\Orgao\DTOs\CriarOrgaoDTO;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Controller fino - apenas recebe request e devolve response
 */
class OrgaoController extends Controller
{
    public function __construct(
        private CriarOrgaoUseCase $criarOrgaoUseCase,
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|integer|exists:empresas,id',
                'uasg' => 'nullable|string|max:255',
                'razao_social' => 'nullable|string|max:255',
                'cnpj' => 'nullable|string|max:18',
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
                'observacoes' => 'nullable|string',
            ], [
                'empresa_id.required' => 'A empresa é obrigatória.',
            ]);

            $dto = CriarOrgaoDTO::fromArray($validated);
            $orgao = $this->criarOrgaoUseCase->executar($dto);

            return response()->json([
                'message' => 'Órgão criado com sucesso!',
                'success' => true,
                'data' => [
                    'id' => $orgao->id,
                    'uasg' => $orgao->uasg,
                    'razao_social' => $orgao->razaoSocial,
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
            Log::error('Erro ao criar órgão', [
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
            $orgaos = $this->orgaoRepository->buscarComFiltros($filtros);

            return response()->json([
                'data' => $orgaos->items(),
                'pagination' => [
                    'current_page' => $orgaos->currentPage(),
                    'per_page' => $orgaos->perPage(),
                    'total' => $orgaos->total(),
                    'last_page' => $orgaos->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar órgãos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar órgãos.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $orgao = $this->orgaoRepository->buscarPorId($id);

            if (!$orgao) {
                return response()->json(['message' => 'Órgão não encontrado.'], 404);
            }

            return response()->json(['data' => [
                'id' => $orgao->id,
                'uasg' => $orgao->uasg,
                'razao_social' => $orgao->razaoSocial,
            ]]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar órgão', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar órgão.'], 500);
        }
    }
}

