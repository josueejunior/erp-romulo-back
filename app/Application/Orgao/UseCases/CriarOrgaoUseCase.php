<?php

namespace App\Application\Orgao\UseCases;

use App\Application\Orgao\DTOs\CriarOrgaoDTO;
use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Órgão
 */
class CriarOrgaoUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(CriarOrgaoDTO $dto): Orgao
    {
        $orgao = new Orgao(
            id: null,
            empresaId: $dto->empresaId,
            uasg: $dto->uasg,
            razaoSocial: $dto->razaoSocial,
            cnpj: $dto->cnpj,
            cep: $dto->cep,
            logradouro: $dto->logradouro,
            numero: $dto->numero,
            bairro: $dto->bairro,
            complemento: $dto->complemento,
            cidade: $dto->cidade,
            estado: $dto->estado,
            email: $dto->email,
            telefone: $dto->telefone,
            emails: $dto->emails,
            telefones: $dto->telefones,
            observacoes: $dto->observacoes,
        );

        return $this->orgaoRepository->criar($orgao);
    }
}

