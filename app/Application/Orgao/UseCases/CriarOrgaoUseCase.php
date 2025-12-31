<?php

namespace App\Application\Orgao\UseCases;

use App\Application\Orgao\DTOs\CriarOrgaoDTO;
use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Application Service: CriarOrgaoUseCase
 * 
 * 🔥 ONDE O TENANT É USADO DE VERDADE
 * 
 * O service pega o tenant_id do TenantContext (setado pelo middleware).
 * O controller não sabe que isso existe.
 */
class CriarOrgaoUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(CriarOrgaoDTO $dto): Orgao
    {
        // Obter tenant_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Por enquanto, mantemos empresaId no DTO para compatibilidade
        // Mas o tenant_id já está disponível no contexto se necessário
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


