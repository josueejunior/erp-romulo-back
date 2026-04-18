<?php

namespace App\Application\Setor\UseCases;

use App\Application\Setor\DTOs\CriarSetorDTO;
use App\Domain\Setor\Entities\Setor;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Criar Setor
 */
class CriarSetorUseCase
{
    public function __construct(
        private SetorRepositoryInterface $setorRepository,
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(CriarSetorDTO $dto): Setor
    {
        $context = TenantContext::get();

        // Validar que o órgão existe e pertence à empresa
        if ($dto->orgaoId) {
            $orgao = $this->orgaoRepository->buscarPorId($dto->orgaoId);
            if (!$orgao) {
                throw new DomainException('Órgão não encontrado.');
            }

            if ($orgao->empresaId !== $context->empresaId) {
                throw new DomainException('Órgão não pertence à empresa ativa.');
            }
        }

        $setor = new Setor(
            id: null,
            empresaId: $context->empresaId, // Get empresaId from context
            orgaoId: $dto->orgaoId,
            nome: $dto->nome,
            email: $dto->email,
            telefone: $dto->telefone,
            observacoes: $dto->observacoes,
        );

        return $this->setorRepository->criar($setor);
    }
}


