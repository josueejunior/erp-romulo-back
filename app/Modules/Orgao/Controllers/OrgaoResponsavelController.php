<?php

namespace App\Modules\Orgao\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\OrgaoResponsavel\UseCases\CriarOrgaoResponsavelUseCase;
use App\Application\OrgaoResponsavel\UseCases\AtualizarOrgaoResponsavelUseCase;
use App\Application\OrgaoResponsavel\DTOs\CriarOrgaoResponsavelDTO;
use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Helpers\PermissionHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domain\Shared\ValueObjects\TenantContext;

/**
 * Controller para gerenciamento de Responsáveis de Órgãos
 */
class OrgaoResponsavelController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private CriarOrgaoResponsavelUseCase $criarResponsavelUseCase,
        private AtualizarOrgaoResponsavelUseCase $atualizarResponsavelUseCase,
        private OrgaoResponsavelRepositoryInterface $responsavelRepository,
    ) {}

    /**
     * Listar responsáveis de um órgão
     */
    public function index(Request $request, int $orgaoId): JsonResponse
    {
        try {
            if (!PermissionHelper::canManageMasterData()) {
                return response()->json(['message' => 'Você não tem permissão para listar responsáveis.'], 403);
            }

            $context = TenantContext::get();
            $responsaveis = $this->responsavelRepository->buscarPorOrgao($orgaoId);

            // Filtrar apenas responsáveis da empresa ativa
            $responsaveis = array_filter($responsaveis, fn($r) => $r->empresaId === $context->empresaId);

            return response()->json([
                'data' => array_map(function ($responsavel) {
                    return [
                        'id' => $responsavel->id,
                        'orgao_id' => $responsavel->orgaoId,
                        'nome' => $responsavel->nome,
                        'cargo' => $responsavel->cargo,
                        'emails' => $responsavel->emails,
                        'telefones' => $responsavel->telefones,
                        'observacoes' => $responsavel->observacoes,
                    ];
                }, $responsaveis),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar responsáveis');
        }
    }

    /**
     * Criar responsável
     */
    public function store(Request $request, int $orgaoId): JsonResponse
    {
        try {
            if (!PermissionHelper::canManageMasterData()) {
                return response()->json(['message' => 'Você não tem permissão para criar responsáveis.'], 403);
            }

            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'cargo' => 'nullable|string|max:255',
                'emails' => 'nullable|array',
                'emails.*' => 'email',
                'telefones' => 'nullable|array',
                'telefones.*' => 'string|max:20',
                'observacoes' => 'nullable|string',
            ]);

            $context = TenantContext::get();
            $validated['orgao_id'] = $orgaoId;
            $validated['empresa_id'] = $context->empresaId;

            $dto = CriarOrgaoResponsavelDTO::fromArray($validated);
            $responsavel = $this->criarResponsavelUseCase->executar($dto);

            return response()->json([
                'message' => 'Responsável criado com sucesso.',
                'data' => [
                    'id' => $responsavel->id,
                    'orgao_id' => $responsavel->orgaoId,
                    'nome' => $responsavel->nome,
                    'cargo' => $responsavel->cargo,
                    'emails' => $responsavel->emails,
                    'telefones' => $responsavel->telefones,
                    'observacoes' => $responsavel->observacoes,
                ],
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar responsável');
        }
    }

    /**
     * Atualizar responsável
     */
    public function update(Request $request, int $orgaoId, int $id): JsonResponse
    {
        try {
            if (!PermissionHelper::canManageMasterData()) {
                return response()->json(['message' => 'Você não tem permissão para atualizar responsáveis.'], 403);
            }

            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'cargo' => 'nullable|string|max:255',
                'emails' => 'nullable|array',
                'emails.*' => 'email',
                'telefones' => 'nullable|array',
                'telefones.*' => 'string|max:20',
                'observacoes' => 'nullable|string',
            ]);

            $validated['orgao_id'] = $orgaoId;

            $dto = CriarOrgaoResponsavelDTO::fromArray($validated);
            $responsavel = $this->atualizarResponsavelUseCase->executar($id, $dto);

            return response()->json([
                'message' => 'Responsável atualizado com sucesso.',
                'data' => [
                    'id' => $responsavel->id,
                    'orgao_id' => $responsavel->orgaoId,
                    'nome' => $responsavel->nome,
                    'cargo' => $responsavel->cargo,
                    'emails' => $responsavel->emails,
                    'telefones' => $responsavel->telefones,
                    'observacoes' => $responsavel->observacoes,
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar responsável');
        }
    }

    /**
     * Deletar responsável
     */
    public function destroy(int $orgaoId, int $id): JsonResponse
    {
        try {
            if (!PermissionHelper::canManageMasterData()) {
                return response()->json(['message' => 'Você não tem permissão para deletar responsáveis.'], 403);
            }

            $context = TenantContext::get();
            $responsavel = $this->responsavelRepository->buscarPorId($id);

            if (!$responsavel) {
                return response()->json(['message' => 'Responsável não encontrado.'], 404);
            }

            if ($responsavel->empresaId !== $context->empresaId) {
                return response()->json(['message' => 'Responsável não pertence à empresa ativa.'], 403);
            }

            $this->responsavelRepository->deletar($id);

            return response()->json(['message' => 'Responsável deletado com sucesso.'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar responsável');
        }
    }
}

