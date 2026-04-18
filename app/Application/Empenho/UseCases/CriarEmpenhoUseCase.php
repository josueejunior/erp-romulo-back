<?php

namespace App\Application\Empenho\UseCases;

use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Exceptions\DomainException;

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
        
        // Calcular prazo de entrega automaticamente se não fornecido
        $prazoEntregaCalculado = $dto->prazoEntregaCalculado;
        if (!$prazoEntregaCalculado && $dto->dataRecebimento) {
            // Buscar modelo Eloquent para acessar prazo_entrega
            $processoModel = \App\Modules\Processo\Models\Processo::find($processo->id);
            if ($processoModel) {
                $prazoEntregaCalculado = $this->calcularPrazoEntrega($processoModel, $dto->dataRecebimento);
            }
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
            prazoEntregaCalculado: $prazoEntregaCalculado,
            valor: $dto->valor,
            concluido: false,
            situacao: $dto->situacao ?? 'aguardando_entrega',
            dataEntrega: null,
            observacoes: $dto->observacoes,
            numeroCte: $dto->numeroCte,
        );

        return $this->empenhoRepository->criar($empenho);
    }

    /**
     * Calcula o prazo de entrega baseado na data de recebimento e prazo do edital
     * 
     * O prazo é calculado somando o prazo de entrega definido no edital/processo
     * à data de recebimento do empenho.
     * 
     * @param \App\Modules\Processo\Models\Processo $processo
     * @param \Carbon\Carbon $dataRecebimento
     * @return \Carbon\Carbon|null
     */
    private function calcularPrazoEntrega($processo, $dataRecebimento): ?\Carbon\Carbon
    {
        if (!$processo || !$processo->prazo_entrega) {
            return null;
        }

        // Parse do prazo_entrega (pode ser "30 dias", "2 meses", etc.)
        $prazoEntrega = $this->parsePrazoEntrega($processo->prazo_entrega);
        if (!$prazoEntrega) {
            return null;
        }

        // Calcular data limite: data_recebimento + prazo_entrega
        return \Carbon\Carbon::parse($dataRecebimento)->add($prazoEntrega);
    }

    /**
     * Faz parse do prazo de entrega do processo
     * 
     * Aceita formatos como:
     * - "30 dias"
     * - "2 meses"
     * - "15 dias úteis"
     * - "90 dias"
     * 
     * @param string $prazoEntrega
     * @return \DateInterval|null
     */
    private function parsePrazoEntrega(string $prazoEntrega): ?\DateInterval
    {
        // Normalizar string
        $prazoEntrega = strtolower(trim($prazoEntrega));
        
        // Extrair número e unidade
        if (preg_match('/(\d+)\s*(dia|dias|mes|meses|mês|mêses|ano|anos)/', $prazoEntrega, $matches)) {
            $quantidade = (int) $matches[1];
            $unidade = $matches[2];
            
            switch ($unidade) {
                case 'dia':
                case 'dias':
                    return new \DateInterval("P{$quantidade}D");
                case 'mes':
                case 'meses':
                case 'mês':
                case 'mêses':
                    return new \DateInterval("P{$quantidade}M");
                case 'ano':
                case 'anos':
                    return new \DateInterval("P{$quantidade}Y");
            }
        }
        
        return null;
    }
}


