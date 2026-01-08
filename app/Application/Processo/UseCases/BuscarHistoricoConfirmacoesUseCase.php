<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;

/**
 * Use Case: Buscar Histórico de Confirmações de Pagamento
 * 
 * ⚠️ NOTA: Ainda trabalha com modelos Eloquent para acessar relacionamentos (itens, notas fiscais).
 */
class BuscarHistoricoConfirmacoesUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @return array Array com histórico de confirmações
     */
    public function executar(int $processoId, int $empresaId): array
    {
        // Buscar processo (domain entity)
        $processoDomain = $this->processoRepository->buscarPorId($processoId);
        
        if (!$processoDomain) {
            throw new NotFoundException('Processo', $processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processoDomain->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Buscar modelo Eloquent para acessar relacionamentos
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId, ['itens']);
        
        if (!$processoModel) {
            throw new NotFoundException('Processo', $processoId);
        }
        
        $historico = [];
        
        // Se o processo já tem data de recebimento, incluir no histórico
        if ($processoModel->data_recebimento_pagamento) {
            // Calcular valores no momento da confirmação
            $receitaTotal = 0;
            $custosDiretos = 0;
            
            foreach ($processoModel->itens as $item) {
                if (in_array($item->status_item, ['aceito', 'aceito_habilitado'])) {
                    $receitaTotal += $item->valor_pago ?? $item->valor_faturado ?? 0;
                }
            }
            
            // Buscar custos diretos (notas fiscais de entrada)
            // Usar buscarPorProcesso que retorna array de entidades de domínio
            $notasEntrada = $this->notaFiscalRepository->buscarPorProcesso($processoId, [
                'tipo' => 'entrada',
                'empresa_id' => $empresaId,
            ]);
            
            $custosDiretos = collect($notasEntrada)->sum(function ($nf) {
                // buscarPorProcesso retorna entidades de domínio (NotaFiscal)
                // A entidade tem propriedade custoTotal (camelCase)
                return $nf instanceof \App\Domain\NotaFiscal\Entities\NotaFiscal 
                    ? ($nf->custoTotal ?? 0) 
                    : 0;
            });
            
            $historico[] = [
                'id' => 1,
                'data_recebimento' => $processoModel->data_recebimento_pagamento->format('Y-m-d'),
                'data_confirmacao' => $processoModel->updated_at->format('Y-m-d H:i:s'),
                'confirmado_por' => $processoModel->updated_by ?? null,
                'receita_total' => round($receitaTotal, 2),
                'custos_diretos' => round($custosDiretos, 2),
                'lucro_bruto' => round($receitaTotal - $custosDiretos, 2),
                'status' => 'confirmado',
            ];
        }
        
        return $historico;
    }
}

