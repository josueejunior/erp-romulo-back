<?php

namespace App\Application\Setor\UseCases;

use App\Application\Setor\DTOs\AtualizarSetorDTO;
use App\Domain\Setor\Entities\Setor;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Atualizar Setor
 */
class AtualizarSetorUseCase
{
    public function __construct(
        private SetorRepositoryInterface $setorRepository,
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(int $id, AtualizarSetorDTO $dto): Setor
    {
        $context = TenantContext::get();

        // Buscar setor existente
        $setorExistente = $this->setorRepository->buscarPorId($id);
        if (!$setorExistente) {
            throw new DomainException('Setor não encontrado.');
        }

        if ($setorExistente->empresaId !== $context->empresaId) {
            throw new DomainException('Setor não pertence à empresa ativa.');
        }

        // Validar órgão se fornecido
        $orgaoId = $dto->orgaoId ?? $setorExistente->orgaoId;
        if ($orgaoId) {
            $orgao = $this->orgaoRepository->buscarPorId($orgaoId);
            if (!$orgao) {
                throw new DomainException('Órgão não encontrado.');
            }

            if ($orgao->empresaId !== $context->empresaId) {
                throw new DomainException('Órgão não pertence à empresa ativa.');
            }
        }

        $setor = new Setor(
            id: $id,
            empresaId: $context->empresaId, // Get empresaId from context
            orgaoId: $orgaoId,
            nome: $dto->nome ?: $setorExistente->nome,
            email: $dto->email ?? $setorExistente->email,
            telefone: $dto->telefone ?? $setorExistente->telefone,
            observacoes: $dto->observacoes ?? $setorExistente->observacoes,
        );

        return $this->setorRepository->atualizar($setor);
    }
}



