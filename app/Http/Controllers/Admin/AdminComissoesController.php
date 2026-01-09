<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Afiliado\UseCases\ListarComissoesAdminUseCase;
use App\Application\Afiliado\UseCases\MarcarComissaoComoPagaUseCase;
use App\Application\Afiliado\UseCases\CriarPagamentoComissaoUseCase;
use App\Application\Afiliado\UseCases\ListarPagamentosComissaoUseCase;
use App\Application\Afiliado\DTOs\CriarPagamentoComissaoDTO;
use App\Domain\Exceptions\DomainException;
use App\Http\Requests\Admin\CriarPagamentoComissaoAdminRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller Admin para gerenciar comissões de afiliados
 * 
 * ✅ DDD: Usa Use Cases e DTOs
 */
class AdminComissoesController extends Controller
{
    public function __construct(
        private readonly ListarComissoesAdminUseCase $listarComissoesAdminUseCase,
        private readonly MarcarComissaoComoPagaUseCase $marcarComissaoComoPagaUseCase,
        private readonly CriarPagamentoComissaoUseCase $criarPagamentoComissaoUseCase,
        private readonly ListarPagamentosComissaoUseCase $listarPagamentosComissaoUseCase,
    ) {}
    /**
     * Lista todas as comissões (com filtros)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $comissoes = $this->listarComissoesAdminUseCase->executar(
                afiliadoId: $request->input('afiliado_id') ? (int) $request->input('afiliado_id') : null,
                status: $request->input('status'),
                dataInicio: $request->input('data_inicio'),
                dataFim: $request->input('data_fim'),
                perPage: (int) $request->input('per_page', 15),
                page: (int) $request->input('page', 1),
            );

            // UseCase retorna LengthAwarePaginator
            if ($comissoes instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                return ApiResponse::paginated($comissoes);
            }
            
            // Fallback: se for array ou collection
            return ApiResponse::collection(is_array($comissoes) ? $comissoes : $comissoes->toArray());

        } catch (\Exception $e) {
            Log::error('Erro ao listar comissões', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Erro ao listar comissões.', 500);
        }
    }

    /**
     * Marca comissão como paga
     */
    public function marcarComoPaga(Request $request, int $comissaoId): JsonResponse
    {
        try {
            $comissao = $this->marcarComissaoComoPagaUseCase->executar(
                comissaoId: $comissaoId,
                dataPagamento: $request->input('data_pagamento'),
                observacoes: $request->input('observacoes'),
            );

            return ApiResponse::success(
                'Comissão marcada como paga.',
                is_array($comissao) ? $comissao : (is_object($comissao) ? [$comissao] : $comissao)
            );

        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);

        } catch (\Exception $e) {
            Log::error('Erro ao marcar comissão como paga', [
                'comissao_id' => $comissaoId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Erro ao marcar comissão como paga.', 500);
        }
    }

    /**
     * Cria pagamento de comissões (agrupa múltiplas comissões)
     * 🔥 DDD: Controller fino - validação via FormRequest
     */
    public function criarPagamento(CriarPagamentoComissaoAdminRequest $request): JsonResponse
    {
        try {
            $dto = CriarPagamentoComissaoDTO::fromArray($request->validated());
            $pagoPor = auth('admin')->id();

            $pagamento = $this->criarPagamentoComissaoUseCase->executar($dto, $pagoPor);

            return ApiResponse::success(
                'Pagamento criado com sucesso.',
                is_array($pagamento) ? $pagamento : (is_object($pagamento) ? [$pagamento] : $pagamento),
                201
            );

        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);

        } catch (\Exception $e) {
            Log::error('Erro ao criar pagamento de comissões', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Erro ao criar pagamento.', 500);
        }
    }

    /**
     * Lista pagamentos de comissões
     */
    public function pagamentos(Request $request): JsonResponse
    {
        try {
            $pagamentos = $this->listarPagamentosComissaoUseCase->executar(
                afiliadoId: $request->input('afiliado_id') ? (int) $request->input('afiliado_id') : null,
                status: $request->input('status'),
                perPage: (int) $request->input('per_page', 15),
                page: (int) $request->input('page', 1),
            );

            // UseCase retorna LengthAwarePaginator
            if ($pagamentos instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                return ApiResponse::paginated($pagamentos);
            }
            
            // Fallback: se for array ou collection
            return ApiResponse::collection(is_array($pagamentos) ? $pagamentos : $pagamentos->toArray());

        } catch (\Exception $e) {
            Log::error('Erro ao listar pagamentos', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Erro ao listar pagamentos.', 500);
        }
    }
}

