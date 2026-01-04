<?php

namespace App\Application\Fornecedor\UseCases;

use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use DomainException;

/**
 * Application Service: CriarFornecedorUseCase
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarFornecedorUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function executar(CriarFornecedorDTO $dto): Fornecedor
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        $fornecedor = new Fornecedor(
            id: null,
            empresaId: $empresaId,
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


