<?php

namespace App\Observers;

use App\Modules\Empenho\Models\Empenho;
use App\Modules\Processo\Services\SaldoService;

class EmpenhoObserver
{
    protected SaldoService $saldoService;

    public function __construct(SaldoService $saldoService)
    {
        $this->saldoService = $saldoService;
    }

    public function created(Empenho $empenho)
    {
        // Calcular prazo de entrega se não foi calculado (sem disparar observers)
        $this->calcularPrazoEntregaSeNecessario($empenho);
        
        // Atualizar situação do empenho baseado em prazos (sem disparar observers)
        $empenho->withoutEvents(function () use ($empenho) {
            $empenho->atualizarSituacao();
        });
        
        // Atualizar documento vinculado (Contrato/AF) - muda situação para "Atendendo"
        // Usar withoutEvents para evitar loops infinitos
        $this->atualizarDocumentoVinculado($empenho);
        
        // Atualizar saldo do processo e valores financeiros dos itens vinculados
        // Não fazer na criação pois ainda não há vínculos
        // Os vínculos serão criados depois e atualizarão os valores
    }
    
    public function updated(Empenho $empenho)
    {
        // Recalcular prazo se data_recebimento mudou
        if ($empenho->wasChanged('data_recebimento')) {
            $this->calcularPrazoEntregaSeNecessario($empenho);
        }
        
        // Atualizar situação do empenho baseado em prazos
        $empenho->atualizarSituacao();
        
        // Atualizar documento vinculado
        $this->atualizarDocumentoVinculado($empenho);
        
        // Atualizar saldo do processo
        $this->atualizarSaldoProcesso($empenho);
    }
    
    public function deleted(Empenho $empenho)
    {
        // Atualizar documento vinculado (reverte situação se necessário)
        $this->atualizarDocumentoVinculado($empenho);
        
        // Atualizar saldo do processo
        $this->atualizarSaldoProcesso($empenho);
    }
    
    /**
     * Calcula prazo de entrega automaticamente se necessário
     */
    protected function calcularPrazoEntregaSeNecessario(Empenho $empenho): void
    {
        if ($empenho->prazo_entrega_calculado || !$empenho->data_recebimento || !$empenho->processo_id) {
            return;
        }

        $processo = $empenho->processo;
        if (!$processo || !$processo->prazo_entrega) {
            return;
        }

        $prazoEntrega = $this->parsePrazoEntrega($processo->prazo_entrega);
        if ($prazoEntrega) {
            $empenho->prazo_entrega_calculado = \Carbon\Carbon::parse($empenho->data_recebimento)
                ->add($prazoEntrega);
            $empenho->saveQuietly(); // Salvar sem disparar observers
        }
    }

    /**
     * Faz parse do prazo de entrega do processo
     */
    protected function parsePrazoEntrega(string $prazoEntrega): ?\DateInterval
    {
        $prazoEntrega = strtolower(trim($prazoEntrega));
        
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
    
    /**
     * Atualiza documento vinculado (Contrato/AF)
     * 
     * Efeitos automáticos:
     * - Atualiza saldo do contrato/AF
     * - Muda situação para "Atendendo" quando empenho é vinculado
     *   (a situação é atualizada automaticamente no método atualizarSaldo())
     * 
     * Usa withoutEvents para evitar loops infinitos
     */
    protected function atualizarDocumentoVinculado(Empenho $empenho): void
    {
        if ($empenho->contrato_id && $empenho->contrato) {
            // Usar withoutEvents para evitar que ContratoObserver dispare e cause loop
            \App\Modules\Contrato\Models\Contrato::withoutEvents(function () use ($empenho) {
                $empenho->contrato->atualizarSaldo();
            });
        }
        
        if ($empenho->autorizacao_fornecimento_id && $empenho->autorizacaoFornecimento) {
            // Usar withoutEvents para evitar loops
            \App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento::withoutEvents(function () use ($empenho) {
                $empenho->autorizacaoFornecimento->atualizarSaldo();
            });
        }
    }

    /**
     * Atualiza saldo do processo e valores financeiros dos itens vinculados
     * 
     * Efeitos automáticos:
     * - Recalcula valores financeiros dos itens que têm vínculos com este empenho
     * - O saldo do processo é calculado dinamicamente via SaldoService
     * 
     * Nota: Na criação do empenho, ainda não há vínculos, então esta função
     * só faz sentido quando o empenho é atualizado ou quando há vínculos existentes.
     */
    protected function atualizarSaldoProcesso(Empenho $empenho): void
    {
        if (!$empenho->processo_id) {
            return;
        }

        // Buscar itens que têm vínculos com este empenho
        // Limitar a query para evitar problemas de memória
        $itensVinculados = \App\Modules\Processo\Models\ProcessoItemVinculo::where('empenho_id', $empenho->id)
            ->with('processoItem')
            ->limit(100) // Limite de segurança
            ->get();

        // Recalcular valores financeiros de cada item vinculado
        // Usar withoutEvents para evitar loops com ProcessoItemObserver
        foreach ($itensVinculados as $vinculo) {
            if ($vinculo->processoItem) {
                // Usar saveQuietly dentro de atualizarValoresFinanceiros não é possível,
                // então vamos usar withoutEvents no nível do modelo
                \App\Modules\Processo\Models\ProcessoItem::withoutEvents(function () use ($vinculo) {
                    $vinculo->processoItem->atualizarValoresFinanceiros();
                });
            }
        }
    }
}

