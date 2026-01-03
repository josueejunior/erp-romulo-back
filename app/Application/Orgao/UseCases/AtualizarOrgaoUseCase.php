<?php

namespace App\Application\Orgao\UseCases;

use App\Application\Orgao\DTOs\AtualizarOrgaoDTO;
use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use DomainException;

/**
 * Use Case: Atualizar Órgão
 */
class AtualizarOrgaoUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(AtualizarOrgaoDTO $dto, int $empresaId): Orgao
    {
        // Buscar órgão existente
        $orgaoExistente = $this->orgaoRepository->buscarPorId($dto->orgaoId);
        
        if (!$orgaoExistente) {
            throw new DomainException('Órgão não encontrado.');
        }

        // Validar se pertence à empresa
        if ($orgaoExistente->empresaId !== $empresaId) {
            throw new DomainException('Órgão não pertence à empresa ativa.');
        }

        // Criar nova instância com dados atualizados (entidade imutável)
        $orgaoAtualizado = new Orgao(
            id: $orgaoExistente->id,
            empresaId: $orgaoExistente->empresaId,
            uasg: $dto->uasg ?? $orgaoExistente->uasg,
            razaoSocial: $dto->razaoSocial ?? $orgaoExistente->razaoSocial,
            cnpj: $dto->cnpj ?? $orgaoExistente->cnpj,
            cep: $dto->cep ?? $orgaoExistente->cep,
            logradouro: $dto->logradouro ?? $orgaoExistente->logradouro,
            numero: $dto->numero ?? $orgaoExistente->numero,
            bairro: $dto->bairro ?? $orgaoExistente->bairro,
            complemento: $dto->complemento ?? $orgaoExistente->complemento,
            cidade: $dto->cidade ?? $orgaoExistente->cidade,
            estado: $dto->estado ?? $orgaoExistente->estado,
            email: $dto->email ?? $orgaoExistente->email,
            telefone: $dto->telefone ?? $orgaoExistente->telefone,
            emails: $dto->emails ?? $orgaoExistente->emails,
            telefones: $dto->telefones ?? $orgaoExistente->telefones,
            observacoes: $dto->observacoes ?? $orgaoExistente->observacoes,
        );

        // Persistir atualização
        return $this->orgaoRepository->atualizar($orgaoAtualizado);
    }
}



