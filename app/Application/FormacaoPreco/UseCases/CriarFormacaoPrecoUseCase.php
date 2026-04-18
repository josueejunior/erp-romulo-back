<?php

namespace App\Application\FormacaoPreco\UseCases;

use App\Application\FormacaoPreco\DTOs\CriarFormacaoPrecoDTO;
use App\Domain\FormacaoPreco\Entities\FormacaoPreco;
use App\Domain\FormacaoPreco\Repositories\FormacaoPrecoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Formação de Preço
 */
class CriarFormacaoPrecoUseCase
{
    public function __construct(
        private FormacaoPrecoRepositoryInterface $formacaoRepository,
    ) {}

    public function executar(CriarFormacaoPrecoDTO $dto): FormacaoPreco
    {
        // Calcular preço recomendado se não fornecido
        $precoRecomendado = $dto->precoRecomendado;
        if ($precoRecomendado === 0.0) {
            // Criar instância temporária para calcular
            $tempFormacao = new FormacaoPreco(
                id: null,
                processoItemId: $dto->processoItemId,
                orcamentoId: $dto->orcamentoId,
                orcamentoItemId: $dto->orcamentoItemId,
                custoProduto: $dto->custoProduto,
                frete: $dto->frete,
                percentualImpostos: $dto->percentualImpostos,
                valorImpostos: $dto->valorImpostos,
                percentualMargem: $dto->percentualMargem,
                valorMargem: $dto->valorMargem,
                precoMinimo: $dto->precoMinimo,
                precoRecomendado: 0.0,
                observacoes: $dto->observacoes,
            );
            $precoRecomendado = $tempFormacao->calcularPrecoRecomendado();
        }

        $formacao = new FormacaoPreco(
            id: null,
            processoItemId: $dto->processoItemId,
            orcamentoId: $dto->orcamentoId,
            orcamentoItemId: $dto->orcamentoItemId,
            custoProduto: $dto->custoProduto,
            frete: $dto->frete,
            percentualImpostos: $dto->percentualImpostos,
            valorImpostos: $dto->valorImpostos,
            percentualMargem: $dto->percentualMargem,
            valorMargem: $dto->valorMargem,
            precoMinimo: $dto->precoMinimo,
            precoRecomendado: $precoRecomendado,
            observacoes: $dto->observacoes,
        );

        return $this->formacaoRepository->criar($formacao);
    }
}

