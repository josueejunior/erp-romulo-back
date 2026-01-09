<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Application\CustoIndireto\DTOs\AtualizarCustoIndiretoDTO;
use App\Domain\CustoIndireto\Entities\CustoIndireto;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Atualizar Custo Indireto
 */
class AtualizarCustoIndiretoUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(AtualizarCustoIndiretoDTO $dto): CustoIndireto
    {
        // Buscar custo indireto existente
        $custoExistente = $this->custoIndiretoRepository->buscarPorId($dto->custoIndiretoId);
        
        if (!$custoExistente) {
            throw new NotFoundException('Custo Indireto', $dto->custoIndiretoId);
        }
        
        // Validar que o custo indireto pertence à empresa
        if ($custoExistente->empresaId !== $dto->empresaId) {
            throw new DomainException('Custo indireto não pertence à empresa ativa.');
        }
        
        // Criar nova instância com valores atualizados (imutabilidade)
        $custoIndiretoAtualizado = new CustoIndireto(
            id: $dto->custoIndiretoId,
            empresaId: $dto->empresaId,
            descricao: $dto->descricao ?? $custoExistente->descricao,
            data: $dto->data ?? $custoExistente->data,
            valor: $dto->valor ?? $custoExistente->valor,
            categoria: $dto->categoria ?? $custoExistente->categoria,
            observacoes: $dto->observacoes ?? $custoExistente->observacoes,
        );
        
        // Persistir alterações
        return $this->custoIndiretoRepository->atualizar($custoIndiretoAtualizado);
    }
}




