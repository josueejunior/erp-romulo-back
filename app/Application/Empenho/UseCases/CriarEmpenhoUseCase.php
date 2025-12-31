<?php

namespace App\Application\Empenho\UseCases;

use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
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
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    public function executar(CriarEmpenhoDTO $dto): Empenho
    {
        // Obter tenant_id do contexto (invisível para o controller)
        $context = TenantContext::get();
        
        // Buscar processo para obter empresa_id e validar regras de negócio
        if (!$dto->processoId) {
            throw new DomainException('Processo é obrigatório para criar empenho.');
        }
        
        $processo = $this->processoRepository->buscarPorId($dto->processoId);
        if (!$processo) {
            throw new DomainException('Processo não encontrado.');
        }
        
        // Validar que o processo está em execução (regra de negócio)
        if (!$processo->estaEmExecucao()) {
            throw new DomainException('Empenhos só podem ser criados para processos em execução.');
        }
        
        // Usar empresaId do processo (não do DTO)
        $empenho = new Empenho(
            id: null,
            empresaId: $processo->empresaId,
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


