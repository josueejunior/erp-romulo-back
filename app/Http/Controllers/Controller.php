<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Empresa;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function getEmpresaAtiva(): ?Empresa
    {
        $user = auth()->user();
        if (!$user || !$user->empresa_ativa_id) {
            return null;
        }

        return Empresa::find($user->empresa_ativa_id);
    }

    protected function getEmpresaAtivaOrFail(): Empresa
    {
        $empresa = $this->getEmpresaAtiva();
        
        if (!$empresa) {
            abort(403, 'Nenhuma empresa ativa selecionada.');
        }

        return $empresa;
    }
}
