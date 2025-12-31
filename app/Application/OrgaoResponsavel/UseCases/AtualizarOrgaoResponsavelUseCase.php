<?php

namespace App\Application\OrgaoResponsavel\UseCases;

use App\Application\OrgaoResponsavel\DTOs\CriarOrgaoResponsavelDTO;
use App\Domain\OrgaoResponsavel\Entities\OrgaoResponsavel;
use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Atualizar Responsável de Órgão
 */
class AtualizarOrgaoResponsavelUseCase
{
    public function __construct(
        private OrgaoResponsavelRepositoryInterface $responsavelRepository,
    ) {}

    public function executar(int $id, CriarOrgaoResponsavelDTO $dto): OrgaoResponsavel
    {
        $context = TenantContext::get();

        $responsavelExistente = $this->responsavelRepository->buscarPorId($id);
        if (!$responsavelExistente) {
            throw new DomainException('Responsável não encontrado.');
        }

        if ($responsavelExistente->empresaId !== $context->empresaId) {
            throw new DomainException('Responsável não pertence à empresa ativa.');
        }

        $responsavel = new OrgaoResponsavel(
            id: $id,
            empresaId: $context->empresaId,
            orgaoId: $dto->orgaoId,
            nome: $dto->nome,
            cargo: $dto->cargo,
            emails: $dto->emails,
            telefones: $dto->telefones,
            observacoes: $dto->observacoes,
        );

        return $this->responsavelRepository->atualizar($responsavel);
    }
}

