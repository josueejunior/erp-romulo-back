<?php

namespace App\Application\Fornecedor\UseCases;

use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Fornecedor
 */
class CriarFornecedorUseCase
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function executar(CriarFornecedorDTO $dto): Fornecedor
    {
        $fornecedor = new Fornecedor(
            id: null,
            empresaId: $dto->empresaId,
            razaoSocial: $dto->razaoSocial,
            cnpj: $dto->cnpj,
            nomeFantasia: $dto->nomeFantasia,
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
            contato: $dto->contato,
            observacoes: $dto->observacoes,
            isTransportadora: $dto->isTransportadora,
        );

        return $this->fornecedorRepository->criar($fornecedor);
    }
}

