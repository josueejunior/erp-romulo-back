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

        if ($notaFiscalModel->situacao === 'paga') {
            throw new DomainException('Nota fiscal já foi paga anteriormente.');
        }

        if ($notaFiscalModel->situacao === 'cancelada') {
            throw new DomainException('Nota fiscal cancelada não pode ser paga.');
        }

        // Nota: Tanto notas de entrada quanto de saída podem ser pagas
        // - Entrada: Pagamento ao fornecedor
        // - Saída: Recebimento do órgão

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
