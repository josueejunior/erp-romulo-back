<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;

abstract class BaseApiController extends Controller
{
    /**
     * Obtém a empresa ativa do usuário autenticado
     */
    protected function getEmpresaAtiva(): ?Empresa
    {
        $user = auth()->user();
        if (!$user || !$user->empresa_ativa_id) {
            return null;
        }

        return Empresa::find($user->empresa_ativa_id);
    }

    /**
     * Obtém a empresa ativa ou retorna erro 403
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        $empresa = $this->getEmpresaAtiva();
        
        if (!$empresa) {
            abort(403, 'Nenhuma empresa ativa selecionada. Selecione uma empresa antes de continuar.');
        }

        return $empresa;
    }
}

