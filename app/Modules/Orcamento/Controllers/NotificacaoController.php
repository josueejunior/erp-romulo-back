<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Orcamento\Domain\Services\NotificacaoDomainService;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    use HasAuthContext;

    private NotificacaoDomainService $service;

    public function __construct(NotificacaoDomainService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /notificacoes
     * Listar notificações do usuário
     */
    public function index(Request $request)
    {
        $usuarioId = $this->getUserId();
        $empresaId = $this->getEmpresaId();
        $apenasNaoLidas = $request->query('nao_lidas', false);

        if ($apenasNaoLidas) {
            $notificacoes = $this->service->obterNaoLidas($usuarioId, $empresaId);
        } else {
            $notificacoes = $this->service->obterTodas($usuarioId, $empresaId);
        }

        return response()->json([
            'success' => true,
            'total' => count($notificacoes),
            'nao_lidas' => $this->service->contarNaoLidas($usuarioId, $empresaId),
            'data' => array_map(fn($n) => $n->toArray(), $notificacoes)
        ]);
    }

    /**
     * GET /notificacoes/{id}
     */
    public function show($id)
    {
        $notificacao = $this->service->obterNaoLidas($this->getUserId(), $this->getEmpresaId());
        $encontrada = array_filter($notificacao, fn($n) => $n->getId() == $id);

        if (empty($encontrada)) {
            return response()->json(['success' => false, 'message' => 'Notificação não encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => reset($encontrada)->toArray()
        ]);
    }

    /**
     * PATCH /notificacoes/{id}/marcar-lida
     */
    public function marcarLida($id)
    {
        $resultado = $this->service->marcarComoLida($id);

        if (!$resultado) {
            return response()->json(['success' => false, 'message' => 'Erro ao marcar como lida'], 400);
        }

        return response()->json(['success' => true, 'message' => 'Notificação marcada como lida']);
    }

    /**
     * PATCH /notificacoes/marcar-multiplas-lidas
     */
    public function marcarMultiplasLidas(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'IDs são obrigatórios'], 400);
        }

        $total = $this->service->marcarMultiplasComoLidas($ids);

        return response()->json([
            'success' => true,
            'total_marcadas' => $total,
            'message' => "Total de {$total} notificações marcadas como lidas"
        ]);
    }

    /**
     * DELETE /notificacoes/{id}
     */
    public function destroy($id)
    {
        $resultado = $this->service->deletar($id);

        if (!$resultado) {
            return response()->json(['success' => false, 'message' => 'Erro ao deletar'], 400);
        }

        return response()->json(['success' => true, 'message' => 'Notificação deletada']);
    }

    /**
     * GET /notificacoes/processos/{processoId}
     */
    public function porProcesso($processoId)
    {
        $empresaId = $this->getEmpresaId();
        $notificacoes = $this->service->obterPorProcesso($processoId, $empresaId);

        return response()->json([
            'success' => true,
            'total' => count($notificacoes),
            'data' => array_map(fn($n) => $n->toArray(), $notificacoes)
        ]);
    }

    /**
     * GET /notificacoes/contar-nao-lidas
     */
    public function contarNaoLidas()
    {
        $usuarioId = $this->getUserId();
        $empresaId = $this->getEmpresaId();
        $total = $this->service->contarNaoLidas($usuarioId, $empresaId);

        return response()->json([
            'success' => true,
            'total_nao_lidas' => $total
        ]);
    }
}
