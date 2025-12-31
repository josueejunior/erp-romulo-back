<?php

namespace App\Application\Empenho\UseCases;

use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Empenho
 */
class CriarEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(CriarEmpenhoDTO $dto): Empenho
    {
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


