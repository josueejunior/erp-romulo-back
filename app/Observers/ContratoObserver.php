<?php

namespace App\Observers;

use App\Models\Contrato;

class ContratoObserver
{
    public function created(Contrato $contrato)
    {
        $contrato->atualizarSaldo();
    }
    
    public function updated(Contrato $contrato)
    {
        $contrato->atualizarSaldo();
    }
    
    public function deleted(Contrato $contrato)
    {
        // Se houver processo relacionado, pode precisar atualizar algo
    }
}

