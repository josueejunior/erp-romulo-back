<?php

namespace App\Observers;

use App\Models\Contrato;

class ContratoObserver
{
    public function created(Contrato $contrato)
    {
        $contrato->atualizarSaldo();
        $this->atualizarVigencia($contrato);
    }
    
    public function updated(Contrato $contrato)
    {
        $contrato->atualizarSaldo();
        $this->atualizarVigencia($contrato);
    }
    
    public function deleted(Contrato $contrato)
    {
        // Se houver processo relacionado, pode precisar atualizar algo
    }

    protected function atualizarVigencia(Contrato $contrato)
    {
        // A vigência já é atualizada no método atualizarSaldo()
        // Este método pode ser expandido se necessário
    }
}

