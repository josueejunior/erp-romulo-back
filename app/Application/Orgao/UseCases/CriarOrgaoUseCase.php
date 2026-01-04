<?php

namespace App\Application\Orgao\UseCases;

use App\Application\Orgao\DTOs\CriarOrgaoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use DomainException;

/**
 * Application Service: CriarOrgaoUseCase
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarOrgaoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(CriarOrgaoDTO $dto): Orgao
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        $orgao = new Orgao(
            id: null,
            empresaId: $empresaId,
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


