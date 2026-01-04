<?php

namespace App\Application\Fornecedor\UseCases;

use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Application Service: CriarFornecedorUseCase
 * 
 * 🔥 ONDE O TENANT É USADO DE VERDADE
 * 
 * O service pega o tenant_id do TenantContext (setado pelo middleware).
 * O controller não sabe que isso existe.
 */
class CriarFornecedorUseCase
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function executar(CriarFornecedorDTO $dto): Fornecedor
    {
        // Obter tenant_id e empresa_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Usa empresaId do DTO se informado, senão tenta do contexto, senão do app container
        $empresaId = $dto->empresaId > 0 
            ? $dto->empresaId 
            : ($context->empresaId ?? (app()->bound('current_empresa_id') ? app('current_empresa_id') : 0));
        
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


