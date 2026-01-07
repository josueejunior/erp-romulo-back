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
        // Calcular prazo de entrega se não foi calculado
        $this->calcularPrazoEntregaSeNecessario($empenho);
        
        // Atualizar situação do empenho baseado em prazos
        $empenho->atualizarSituacao();
        
        // Atualizar documento vinculado (Contrato/AF) - muda situação para "Atendendo"
        $this->atualizarDocumentoVinculado($empenho);
        
        // Atualizar saldo do processo e valores financeiros dos itens vinculados
        $this->atualizarSaldoProcesso($empenho);
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
     */
    protected function atualizarDocumentoVinculado(Empenho $empenho): void
    {
        if ($empenho->contrato_id && $empenho->contrato) {
            // atualizarSaldo() já atualiza a situação automaticamente
            $empenho->contrato->atualizarSaldo();
        }
        
        if ($empenho->autorizacao_fornecimento_id && $empenho->autorizacaoFornecimento) {
            // atualizarSaldo() já atualiza a situação automaticamente
            $empenho->autorizacaoFornecimento->atualizarSaldo();
        }
    }

    /**
     * Atualiza saldo do processo e valores financeiros dos itens vinculados
     * 
     * Efeitos automáticos:
     * - Recalcula valores financeiros dos itens que têm vínculos com este empenho
     * - O saldo do processo é calculado dinamicamente via SaldoService
     */
    protected function atualizarSaldoProcesso(Empenho $empenho): void
    {
        if (!$empenho->processo_id) {
            return;
        }

        // Buscar itens que têm vínculos com este empenho
        $itensVinculados = \App\Modules\Processo\Models\ProcessoItemVinculo::where('empenho_id', $empenho->id)
            ->with('processoItem')
            ->get();

        // Recalcular valores financeiros de cada item vinculado
        foreach ($itensVinculados as $vinculo) {
            if ($vinculo->processoItem) {
                $vinculo->processoItem->atualizarValoresFinanceiros();
            }
        }
    }
}

