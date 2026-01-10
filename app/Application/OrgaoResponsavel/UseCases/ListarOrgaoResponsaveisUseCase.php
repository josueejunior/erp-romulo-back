<?php

namespace App\Application\OrgaoResponsavel\UseCases;

use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Responsáveis de Órgão
 */
class ListarOrgaoResponsaveisUseCase
{
    public function __construct(
        private OrgaoResponsavelRepositoryInterface $responsavelRepository,
    ) {}

    public function executar(int $orgaoId, array $filtros = []): LengthAwarePaginator
    {
        $context = TenantContext::get();

        return $this->responsavelRepository->buscarComFiltros([
            'orgao_id' => $orgaoId,
            'empresa_id' => $context->empresaId,
            'per_page' => $filtros['per_page'] ?? 1000, // Buscar todos por padrão
        ]);
    }
}



