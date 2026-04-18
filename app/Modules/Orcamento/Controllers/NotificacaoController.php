<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Orcamento\Domain\Services\NotificacaoDomainService;
use App\Http\Requests\Notificacao\MarcarMultiplasLidasRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gerenciamento de notificações
 * 
 * ✅ DDD Enterprise-Grade:
 * - Valida ownership em todas as operações
 * - Usa FormRequest para validação
 * - Domain Service retorna Collection (não array)
 * - Respostas padronizadas
 */
class NotificacaoController extends Controller
{
    use HasAuthContext;

    public function __construct(
        private NotificacaoDomainService $service,
    ) {}

    /**
     * GET /notificacoes
     * Listar notificações do usuário
     * 
     * ✅ DDD: Controller apenas orquestra, Domain Service retorna Collection
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $usuarioId = $this->getUserId();
            $empresaId = $this->getEmpresaId();
            $apenasNaoLidas = $request->query('nao_lidas', false);

            // Domain Service retorna Collection (não array)
            $notificacoes = $apenasNaoLidas
                ? $this->service->obterNaoLidas($usuarioId, $empresaId)
                : $this->service->obterTodas($usuarioId, $empresaId);

            return $this->success([
                'total' => $notificacoes->count(),
                'nao_lidas' => $this->service->contarNaoLidas($usuarioId, $empresaId),
                'data' => $notificacoes->map(fn($n) => $n->toArray())->values()->all(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar notificações: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('Erro ao listar notificações', 500);
        }
    }

    /**
     * GET /notificacoes/{id}
     * 
     * ✅ DDD: Valida ownership no Domain Service
     * Busca por ID, não filtra manualmente
     */
    public function show(int $id): JsonResponse
    {
        try {
            $notificacao = $this->service->obterPorId(
                $id,
                $this->getUserId(),
                $this->getEmpresaId()
            );

            if (!$notificacao) {
                return $this->error('Notificação não encontrada', 404);
            }

            return $this->success([
                'data' => $notificacao->toArray(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificação: ' . $e->getMessage(), [
                'notificacao_id' => $id,
                'exception' => $e,
            ]);
            return $this->error('Erro ao buscar notificação', 500);
        }
    }

    /**
     * PATCH /notificacoes/{id}/marcar-lida
     * 
     * ✅ DDD: Valida ownership no Domain Service
     */
    public function marcarLida(int $id): JsonResponse
    {
        try {
            $resultado = $this->service->marcarComoLida(
                $id,
                $this->getUserId(),
                $this->getEmpresaId()
            );

            if (!$resultado) {
                return $this->error('Notificação não encontrada ou não pertence ao usuário', 404);
            }

            return $this->success([
                'message' => 'Notificação marcada como lida',
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao marcar notificação como lida: ' . $e->getMessage(), [
                'notificacao_id' => $id,
                'exception' => $e,
            ]);
            return $this->error('Erro ao marcar notificação como lida', 500);
        }
    }

    /**
     * PATCH /notificacoes/marcar-multiplas-lidas
     * 
     * ✅ DDD: Validação via FormRequest, valida ownership no Domain Service
     */
    public function marcarMultiplasLidas(MarcarMultiplasLidasRequest $request): JsonResponse
    {
        try {
            // FormRequest já validou os dados
            $ids = $request->validated()['ids'];

            // Domain Service valida ownership de cada notificação
            $total = $this->service->marcarMultiplasComoLidas(
                $ids,
                $this->getUserId(),
                $this->getEmpresaId()
            );

            return $this->success([
                'total_marcadas' => $total,
                'total_solicitadas' => count($ids),
                'message' => "Total de {$total} notificações marcadas como lidas",
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao marcar múltiplas notificações como lidas: ' . $e->getMessage(), [
                'ids' => $request->input('ids'),
                'exception' => $e,
            ]);
            return $this->error('Erro ao marcar notificações como lidas', 500);
        }
    }

    /**
     * DELETE /notificacoes/{id}
     * 
     * ✅ DDD: Valida ownership no Domain Service
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $resultado = $this->service->deletar(
                $id,
                $this->getUserId(),
                $this->getEmpresaId()
            );

            if (!$resultado) {
                return $this->error('Notificação não encontrada ou não pertence ao usuário', 404);
            }

            return $this->success([
                'message' => 'Notificação deletada com sucesso',
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Erro ao deletar notificação: ' . $e->getMessage(), [
                'notificacao_id' => $id,
                'exception' => $e,
            ]);
            return $this->error('Erro ao deletar notificação', 500);
        }
    }

    /**
     * GET /notificacoes/processos/{processoId}
     * 
     * ✅ DDD: Domain Service retorna Collection
     */
    public function porProcesso(int $processoId): JsonResponse
    {
        try {
            $empresaId = $this->getEmpresaId();
            $notificacoes = $this->service->obterPorProcesso($processoId, $empresaId);

            return $this->success([
                'total' => $notificacoes->count(),
                'data' => $notificacoes->map(fn($n) => $n->toArray())->values()->all(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificações por processo: ' . $e->getMessage(), [
                'processo_id' => $processoId,
                'exception' => $e,
            ]);
            return $this->error('Erro ao buscar notificações', 500);
        }
    }

    /**
     * GET /notificacoes/contar-nao-lidas
     * 
     * ✅ DDD: Endpoint específico para contagem
     */
    public function contarNaoLidas(): JsonResponse
    {
        try {
            $usuarioId = $this->getUserId();
            $empresaId = $this->getEmpresaId();
            $total = $this->service->contarNaoLidas($usuarioId, $empresaId);

            return $this->success([
                'total_nao_lidas' => $total,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao contar notificações não lidas: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('Erro ao contar notificações', 500);
        }
    }

    /**
     * GET /notificacoes/nao-lidas
     * 
     * ✅ DDD: Endpoint específico para notificações não lidas
     * Separa responsabilidades do index
     */
    public function naoLidas(): JsonResponse
    {
        try {
            $usuarioId = $this->getUserId();
            $empresaId = $this->getEmpresaId();
            $notificacoes = $this->service->obterNaoLidas($usuarioId, $empresaId);

            return $this->success([
                'total' => $notificacoes->count(),
                'data' => $notificacoes->map(fn($n) => $n->toArray())->values()->all(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificações não lidas: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('Erro ao buscar notificações', 500);
        }
    }

    /**
     * Helper para respostas de sucesso
     */
    private function success(array $data = [], int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$data,
        ], $statusCode);
    }

    /**
     * Helper para respostas de erro
     */
    private function error(string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}
