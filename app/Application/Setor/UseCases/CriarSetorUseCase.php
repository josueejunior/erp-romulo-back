<?php

namespace App\Application\Setor\UseCases;

use App\Application\Setor\DTOs\CriarSetorDTO;
use App\Domain\Setor\Entities\Setor;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Setor
 */
class CriarSetorUseCase
{
    public function __construct(
        private SetorRepositoryInterface $setorRepository,
    ) {}

    public function executar(CriarSetorDTO $dto): Setor
    {
        $setor = new Setor(
            id: null,
            empresaId: $dto->empresaId,
            orgaoId: $dto->orgaoId,
            nome: $dto->nome,
            email: $dto->email,
            telefone: $dto->telefone,
            observacoes: $dto->observacoes,
        );

        return $this->setorRepository->criar($setor);
    }
}


