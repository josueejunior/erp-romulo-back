<?php

namespace App\Application\OrgaoResponsavel\UseCases;

use App\Application\OrgaoResponsavel\DTOs\CriarOrgaoResponsavelDTO;
use App\Domain\OrgaoResponsavel\Entities\OrgaoResponsavel;
use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case: Atualizar Responsável de Órgão
 */
class AtualizarOrgaoResponsavelUseCase
{
    public function __construct(
        private OrgaoResponsavelRepositoryInterface $responsavelRepository,
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(int $id, CriarOrgaoResponsavelDTO $dto): OrgaoResponsavel
    {
        $context = TenantContext::get();

        // Validar que o órgão existe e pertence à empresa
        $orgao = $this->orgaoRepository->buscarPorId($dto->orgaoId);
        if (!$orgao) {
            throw new DomainException('Órgão não encontrado.');
        }

        if ($orgao->empresaId !== $context->empresaId) {
            throw new DomainException('Órgão não pertence à empresa ativa.');
        }

        // Buscar responsável existente
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

