<?php

namespace App\Application\Empenho\UseCases;

use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Application Service: CriarEmpenhoUseCase
 * 
 * 🔥 ONDE O TENANT É USADO DE VERDADE
 * 
 * O service pega o tenant_id do TenantContext (setado pelo middleware).
 * O controller não sabe que isso existe.
 */
class CriarEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(CriarEmpenhoDTO $dto): Empenho
    {
        // Obter tenant_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Por enquanto, mantemos empresaId no DTO para compatibilidade
        // Mas o tenant_id já está disponível no contexto se necessário
        $empenho = new Empenho(
            id: null,
            empresaId: $dto->empresaId,
            processoId: $dto->processoId,
            contratoId: $dto->contratoId,
            autorizacaoFornecimentoId: $dto->autorizacaoFornecimentoId,
            numero: $dto->numero,
            data: $dto->data,
            dataRecebimento: $dto->dataRecebimento,
            prazoEntregaCalculado: $dto->prazoEntregaCalculado,
            valor: $dto->valor,
            concluido: false,
            situacao: $dto->situacao,
            dataEntrega: null,
            observacoes: $dto->observacoes,
            numeroCte: $dto->numeroCte,
        );

        return $this->empenhoRepository->criar($empenho);
    }
}


