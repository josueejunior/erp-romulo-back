<?php

namespace App\Observers;

use App\Modules\Processo\Models\ProcessoItemVinculo;

class ProcessoItemVinculoObserver
{
    /**
     * Handle the ProcessoItemVinculo "created" event.
     */
    public function created(ProcessoItemVinculo $vinculo): void
    {
        $this->atualizarItem($vinculo);
    }

    /**
     * Handle the ProcessoItemVinculo "updated" event.
     */
    public function updated(ProcessoItemVinculo $vinculo): void
    {
        $this->atualizarItem($vinculo);
    }

    /**
     * Handle the ProcessoItemVinculo "deleted" event.
     */
    public function deleted(ProcessoItemVinculo $vinculo): void
    {
        $this->atualizarItem($vinculo);
    }

    /**
     * Atualiza valores financeiros do item quando vínculo muda
     */
    protected function atualizarItem(ProcessoItemVinculo $vinculo): void
    {
        if ($vinculo->processoItem) {
            $vinculo->processoItem->atualizarValoresFinanceiros();
        }
    }
}


