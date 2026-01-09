<?php

namespace App\Modules\Afiliado\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Afiliado\DTOs\CriarAfiliadoDTO;
use App\Application\Afiliado\DTOs\AtualizarAfiliadoDTO;
use App\Application\Afiliado\UseCases\ListarAfiliadosUseCase;
use App\Application\Afiliado\UseCases\CriarAfiliadoUseCase;
use App\Application\Afiliado\UseCases\AtualizarAfiliadoUseCase;
use App\Application\Afiliado\UseCases\BuscarAfiliadoUseCase;
use App\Application\Afiliado\UseCases\ExcluirAfiliadoUseCase;
use App\Application\Afiliado\UseCases\BuscarDetalhesAfiliadoUseCase;
use App\Application\Afiliado\UseCases\ValidarCupomAfiliadoUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;

/**
 * Controller de Afiliados (Painel Administrativo)
 * 
 * ✅ DDD: Usa Use Cases, DTOs
 */
class AfiliadoController extends BaseApiController
{
    public function __construct(
        private ListarAfiliadosUseCase $listarAfiliadosUseCase,
        private CriarAfiliadoUseCase $criarAfiliadoUseCase,
        private AtualizarAfiliadoUseCase $atualizarAfiliadoUseCase,
        private BuscarAfiliadoUseCase $buscarAfiliadoUseCase,
        private ExcluirAfiliadoUseCase $excluirAfiliadoUseCase,
        private BuscarDetalhesAfiliadoUseCase $buscarDetalhesAfiliadoUseCase,
        private ValidarCupomAfiliadoUseCase $validarCupomAfiliadoUseCase,
    ) {}

    /**
     * Lista todos os afiliados
     * 
     * GET /api/v1/admin/afiliados
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $afiliados = $this->listarAfiliadosUseCase->executar(
                search: $request->get('search'),
                ativo: $request->has('ativo') ? filter_var($request->get('ativo'), FILTER_VALIDATE_BOOLEAN) : null,
                perPage: (int) $request->get('per_page', 15),
                page: (int) $request->get('page', 1),
            );

            return response()->json([
                'data' => $afiliados->items(),
                'meta' => [
                    'current_page' => $afiliados->currentPage(),
                    'last_page' => $afiliados->lastPage(),
                    'per_page' => $afiliados->perPage(),
                    'total' => $afiliados->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::index - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao listar afiliados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria um novo afiliado
     * 
     * POST /api/v1/admin/afiliados
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nome' => 'required|string|max:255',
                'documento' => 'required|string|max:20',
                'tipo_documento' => 'required|in:cpf,cnpj',
                'email' => 'required|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'percentual_desconto' => 'nullable|numeric|min:0|max:100',
                'percentual_comissao' => 'nullable|numeric|min:0|max:100',
                'contas_bancarias' => 'nullable|array',
                'contas_bancarias.*.banco' => 'nullable|string|max:100',
                'contas_bancarias.*.agencia' => 'nullable|string|max:20',
                'contas_bancarias.*.conta' => 'nullable|string|max:30',
                'contas_bancarias.*.tipo_conta' => 'nullable|in:corrente,poupanca',
                'contas_bancarias.*.pix' => 'nullable|string|max:255',
            ]);

            $dto = CriarAfiliadoDTO::fromArray($request->all());
            $afiliado = $this->criarAfiliadoUseCase->executar($dto);

            return response()->json([
                'message' => 'Afiliado criado com sucesso!',
                'data' => $afiliado,
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::store - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao criar afiliado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca um afiliado por ID
     * 
     * GET /api/v1/admin/afiliados/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $afiliado = $this->buscarAfiliadoUseCase->executar($id);

            // Garantir que contas_bancarias sempre seja um array
            $afiliadoData = $afiliado->toArray();
            if (empty($afiliadoData['contas_bancarias']) && 
                ($afiliadoData['banco'] || $afiliadoData['agencia'] || $afiliadoData['conta'] || $afiliadoData['pix'])) {
                // Migrar dados antigos para novo formato
                $afiliadoData['contas_bancarias'] = [[
                    'banco' => $afiliadoData['banco'] ?? '',
                    'agencia' => $afiliadoData['agencia'] ?? '',
                    'conta' => $afiliadoData['conta'] ?? '',
                    'tipo_conta' => $afiliadoData['tipo_conta'] ?? '',
                    'pix' => $afiliadoData['pix'] ?? '',
                ]];
            } elseif (empty($afiliadoData['contas_bancarias'])) {
                $afiliadoData['contas_bancarias'] = [];
            }

            return response()->json([
                'data' => $afiliadoData,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::show - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao buscar afiliado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza um afiliado
     * 
     * PUT /api/v1/admin/afiliados/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'nome' => 'sometimes|string|max:255',
                'documento' => 'sometimes|string|max:20',
                'tipo_documento' => 'sometimes|in:cpf,cnpj',
                'email' => 'sometimes|email|max:255',
                'percentual_desconto' => 'nullable|numeric|min:0|max:100',
                'percentual_comissao' => 'nullable|numeric|min:0|max:100',
                'contas_bancarias' => 'nullable|array',
                'contas_bancarias.*.banco' => 'nullable|string|max:100',
                'contas_bancarias.*.agencia' => 'nullable|string|max:20',
                'contas_bancarias.*.conta' => 'nullable|string|max:30',
                'contas_bancarias.*.tipo_conta' => 'nullable|in:corrente,poupanca',
                'contas_bancarias.*.pix' => 'nullable|string|max:255',
            ]);

            $dto = AtualizarAfiliadoDTO::fromArray($id, $request->all());
            $afiliado = $this->atualizarAfiliadoUseCase->executar($dto);

            return response()->json([
                'message' => 'Afiliado atualizado com sucesso!',
                'data' => $afiliado,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::update - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao atualizar afiliado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exclui um afiliado
     * 
     * DELETE /api/v1/admin/afiliados/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->excluirAfiliadoUseCase->executar($id);

            return response()->json([
                'message' => 'Afiliado excluído com sucesso!',
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::destroy - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao excluir afiliado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca detalhes do afiliado com estatísticas
     * 
     * GET /api/v1/admin/afiliados/{id}/detalhes
     */
    public function detalhes(Request $request, int $id): JsonResponse
    {
        try {
            $resultado = $this->buscarDetalhesAfiliadoUseCase->executar(
                id: $id,
                status: $request->get('status'),
                dataInicio: $request->get('data_inicio'),
                dataFim: $request->get('data_fim'),
                perPage: (int) $request->get('per_page', 15),
                page: (int) $request->get('page', 1),
            );

            return response()->json([
                'data' => $resultado['afiliado'],
                'indicacoes' => [
                    'data' => $resultado['indicacoes']->items(),
                    'meta' => [
                        'current_page' => $resultado['indicacoes']->currentPage(),
                        'last_page' => $resultado['indicacoes']->lastPage(),
                        'per_page' => $resultado['indicacoes']->perPage(),
                        'total' => $resultado['indicacoes']->total(),
                    ],
                ],
                'estatisticas' => $resultado['estatisticas'],
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::detalhes - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao buscar detalhes do afiliado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Valida um código de afiliado (para checkout)
     * 
     * POST /api/v1/cupom/validar
     */
    public function validarCupom(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'codigo' => 'required|string|max:50',
                'valor_plano' => 'nullable|numeric|min:0',
            ]);

            $codigo = $request->get('codigo');
            $valorPlano = $request->get('valor_plano');

            if ($valorPlano) {
                $resultado = $this->validarCupomAfiliadoUseCase->calcularDesconto($codigo, $valorPlano);
            } else {
                $resultado = $this->validarCupomAfiliadoUseCase->executar($codigo);
            }

            return response()->json([
                'message' => 'Cupom válido!',
                'data' => $resultado,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => ['valido' => false],
            ], 422);
        } catch (\Exception $e) {
            \Log::error('AfiliadoController::validarCupom - Erro', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao validar cupom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}



