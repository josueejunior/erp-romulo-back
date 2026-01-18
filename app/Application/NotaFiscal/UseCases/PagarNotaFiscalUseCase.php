<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

class PagarNotaFiscalUseCase
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
        private \App\Modules\Processo\Services\SaldoService $saldoService
    ) {}

    public function executar(int $notaFiscalId, int $empresaId): void
    {
        $notaFiscalModel = \App\Modules\NotaFiscal\Models\NotaFiscal::find($notaFiscalId);
        
        if (!$notaFiscalModel || $notaFiscalModel->empresa_id !== $empresaId) {
            throw new DomainException('Nota fiscal não encontrada.');
        }

        // Regra de negócio: Apenas notas de saída podem ser pagas (recebidas do órgão)
        if ($notaFiscalModel->tipo !== 'saida') {
            throw new DomainException('Apenas notas fiscais de saída podem ser marcadas como pagas.');
        }

        $notaFiscalModel->situacao = 'paga';
        $notaFiscalModel->data_pagamento = Carbon::now();
        $notaFiscalModel->save();

        // 🔥 Atualizar saldos dos itens e documentos vinculados
        // O SaldoService já tem lógica para isso
        $this->saldoService->registrarPagamento($notaFiscalModel);
        
        // Recalcular valores financeiros dos itens do processo para garantir integridade
        if ($notaFiscalModel->processo) {
            $this->saldoService->recalcularValoresFinanceirosItens($notaFiscalModel->processo);
        }
    }
}
