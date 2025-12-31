<?php

namespace App\Application\Fornecedor\UseCases;

use App\Application\Fornecedor\DTOs\AtualizarFornecedorDTO;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use DomainException;

/**
 * Use Case: Atualizar Fornecedor
 * Orquestra a atualização de fornecedor
 */
class AtualizarFornecedorUseCase
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(AtualizarFornecedorDTO $dto, int $empresaId): Fornecedor
    {
        // Buscar fornecedor existente
        $fornecedorExistente = $this->fornecedorRepository->buscarPorId($dto->fornecedorId);
        
        if (!$fornecedorExistente) {
            throw new DomainException('Fornecedor não encontrado.');
        }

        // Validar se pertence à empresa
        if ($fornecedorExistente->empresaId !== $empresaId) {
            throw new DomainException('Fornecedor não pertence à empresa ativa.');
        }

        // Criar nova instância com dados atualizados (entidade imutável)
        $fornecedorAtualizado = new Fornecedor(
            id: $fornecedorExistente->id,
            empresaId: $fornecedorExistente->empresaId,
            razaoSocial: $dto->razaoSocial ?? $fornecedorExistente->razaoSocial,
            cnpj: $dto->cnpj ?? $fornecedorExistente->cnpj,
            nomeFantasia: $dto->nomeFantasia ?? $fornecedorExistente->nomeFantasia,
            cep: $dto->cep ?? $fornecedorExistente->cep,
            logradouro: $dto->logradouro ?? $fornecedorExistente->logradouro,
            numero: $dto->numero ?? $fornecedorExistente->numero,
            bairro: $dto->bairro ?? $fornecedorExistente->bairro,
            complemento: $dto->complemento ?? $fornecedorExistente->complemento,
            cidade: $dto->cidade ?? $fornecedorExistente->cidade,
            estado: $dto->estado ?? $fornecedorExistente->estado,
            email: $dto->email ?? $fornecedorExistente->email,
            telefone: $dto->telefone ?? $fornecedorExistente->telefone,
            emails: $dto->emails ?? $fornecedorExistente->emails,
            telefones: $dto->telefones ?? $fornecedorExistente->telefones,
            contato: $dto->contato ?? $fornecedorExistente->contato,
            observacoes: $dto->observacoes ?? $fornecedorExistente->observacoes,
            isTransportadora: $dto->isTransportadora ?? $fornecedorExistente->isTransportadora,
        );

        // Persistir atualização
        return $this->fornecedorRepository->atualizar($fornecedorAtualizado);
    }
}


