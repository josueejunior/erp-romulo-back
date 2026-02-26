<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\SupportTicket\UseCases\ListarTicketsAdminUseCase;
use App\Application\SupportTicket\UseCases\BuscarTicketAdminUseCase;
use App\Application\SupportTicket\UseCases\AtualizarStatusTicketAdminUseCase;
use App\Application\SupportTicket\UseCases\AdicionarRespostaTicketAdminUseCase;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UploadController as UploadControllerBase;
use App\Http\Responses\ApiResponse;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listagem e atendimento de tickets de suporte no painel admin.
 * DDD: controller fino — delega para Use Cases.
 */
class AdminSupportTicketController extends Controller
{
    public function __construct(
        private readonly ListarTicketsAdminUseCase $listarTicketsAdminUseCase,
        private readonly BuscarTicketAdminUseCase $buscarTicketAdminUseCase,
        private readonly AtualizarStatusTicketAdminUseCase $atualizarStatusTicketAdminUseCase,
        private readonly AdicionarRespostaTicketAdminUseCase $adicionarRespostaTicketAdminUseCase,
    ) {}

    /**
     * Lista tickets. Sem tenant_id = todas as empresas. Com tenant_id = só daquela empresa.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = [
            'tenant_id' => $request->query('tenant_id'),
            'per_page' => $request->query('per_page', 20),
            'page' => $request->query('page', 1),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        try {
            $result = $this->listarTicketsAdminUseCase->executar($filtros);
        } catch (NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        $data = array_map(function (array $item) {
            $item['anexo_view_url'] = ! empty($item['anexo_url'])
                ? UploadControllerBase::signedUrlForAnexo($item['anexo_url'])
                : null;
            return $item;
        }, $result['data']);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Exibe um ticket com todas as respostas.
     * Com tenant_id: busca no banco daquele tenant. Sem: busca em todos (link direto).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->get('tenant_id', 0);

        try {
            $data = $this->buscarTicketAdminUseCase->executar($id, $tenantId);
        } catch (NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        $data['anexo_view_url'] = ! empty($data['anexo_url'] ?? null)
            ? UploadControllerBase::signedUrlForAnexo($data['anexo_url'])
            : null;

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Atualiza o status do ticket. Requer tenant_id (query ou body).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->query('tenant_id') ?? $request->input('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return ApiResponse::error('Informe tenant_id (ID da empresa).', 400);
        }

        $validated = $request->validate([
            'status' => 'required|in:aberto,em_atendimento,resolvido',
        ]);

        try {
            $data = $this->atualizarStatusTicketAdminUseCase->executar($id, $tenantId, $validated['status']);
        } catch (NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Adiciona uma resposta do admin ao ticket. Requer tenant_id (query ou body).
     */
    public function storeResponse(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->query('tenant_id') ?? $request->input('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return ApiResponse::error('Informe tenant_id (ID da empresa).', 400);
        }

        $validated = $request->validate([
            'mensagem' => 'required|string|max:5000',
        ]);

        try {
            $result = $this->adicionarRespostaTicketAdminUseCase->executar($id, $tenantId, $validated['mensagem']);
        } catch (NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
