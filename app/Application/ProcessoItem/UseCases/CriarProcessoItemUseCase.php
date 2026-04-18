<?php

namespace App\Application\ProcessoItem\UseCases;

use App\Application\ProcessoItem\DTOs\CriarProcessoItemDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\ProcessoItem\Entities\ProcessoItem;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\ProcessoEmExecucaoException;
use DomainException;

/**
 * Use Case: Criar Item de Processo
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarProcessoItemUseCase
{
    use HasApplicationContext;
    public function __construct(
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(CriarProcessoItemDTO $dto): ProcessoItem
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId ?? null);
        
        // Buscar processo existente
        $processo = $this->processoRepository->buscarPorId($dto->processoId);
        
        if (!$processo) {
            throw new NotFoundException('Processo', $dto->processoId);
        }
        
        // Validar que o processo pertence à empresa (no UseCase, não no repositório)
        if ($processo->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Validar regra de negócio: processo não pode estar em execução
        if ($processo->estaEmExecucao()) {
            throw new ProcessoEmExecucaoException('Não é possível editar itens de processos em execução.', $dto->processoId);
        }
        
        // Se número do item não foi fornecido, calcular próximo número
        $numeroItem = $dto->numeroItem;
        if (!$numeroItem) {
            $processoModel = $this->processoRepository->buscarModeloPorId($dto->processoId, ['itens']);
            if ($processoModel) {
                $ultimoItem = $processoModel->itens()->orderBy('numero_item', 'desc')->first();
                $numeroItem = $ultimoItem ? ($ultimoItem->numero_item + 1) : 1;
            } else {
                $numeroItem = 1;
            }
        }
        
        // Calcular valor estimado total
        $valorEstimadoTotal = ($dto->quantidade > 0 && $dto->valorEstimado > 0) 
            ? round($dto->quantidade * $dto->valorEstimado, 2) 
            : 0.0;
        
        // Criar entidade ProcessoItem (empresaId vem do contexto, não é inferido)
        $processoItem = new ProcessoItem(
            id: null,
            processoId: $dto->processoId,
            empresaId: $empresaId,
            fornecedorId: $dto->fornecedorId,
            transportadoraId: $dto->transportadoraId,
            numeroItem: (string) $numeroItem,
            codigoInterno: $dto->codigoInterno,
            quantidade: $dto->quantidade,
            unidade: $dto->unidade,
            especificacaoTecnica: $dto->especificacaoTecnica,
            marcaModeloReferencia: $dto->marcaModeloReferencia,
            observacoesEdital: $dto->observacoesEdital,
            exigeAtestado: $dto->exigeAtestado,
            quantidadeMinimaAtestado: $dto->quantidadeMinimaAtestado,
            quantidadeAtestadoCapTecnica: $dto->quantidadeAtestadoCapTecnica,
            valorEstimado: $dto->valorEstimado,
            valorEstimadoTotal: $valorEstimadoTotal,
            statusItem: 'pendente',
            observacoes: $dto->observacoes,
        );
        
        // Persistir item
        return $this->processoItemRepository->criar($processoItem);
    }
}

