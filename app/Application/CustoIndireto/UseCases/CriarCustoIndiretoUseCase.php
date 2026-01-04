<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Application\CustoIndireto\DTOs\CriarCustoIndiretoDTO;
use App\Domain\CustoIndireto\Entities\CustoIndireto;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Criar Custo Indireto
 */
class CriarCustoIndiretoUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoRepository,
    ) {}

    public function executar(CriarCustoIndiretoDTO $dto): CustoIndireto
    {
        // Obter tenant_id e empresa_id do contexto
        $context = TenantContext::get();
        
        // Usa empresaId do DTO se informado, senão usa do contexto
        $empresaId = $dto->empresaId > 0 ? $dto->empresaId : ($context->empresaId ?? 0);
        
        $custo = new CustoIndireto(
            id: null,
            empresaId: $empresaId,
            descricao: $dto->descricao,
            data: $dto->data,
            valor: $dto->valor,
            categoria: $dto->categoria,
            observacoes: $dto->observacoes,
        );

        return $this->custoRepository->criar($custo);
    }
}



