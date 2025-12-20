<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;

abstract class BaseApiController extends Controller
{
    /**
     * Obtém a empresa do usuário autenticado
     * Cada usuário tem apenas UMA empresa associada
     */
    protected function getEmpresaAtiva(): ?Empresa
    {
        $user = auth()->user();
        
        if (!$user) {
            return null;
        }

        // Primeiro, tentar usar empresa_ativa_id se existir (compatibilidade)
        if ($user->empresa_ativa_id) {
            $empresa = Empresa::find($user->empresa_ativa_id);
            if ($empresa) {
                return $empresa;
            }
        }

        // Se não tiver empresa_ativa_id, pegar a primeira empresa do relacionamento
        $empresa = $user->empresas()->first();
        
        if ($empresa) {
            // Atualizar empresa_ativa_id para manter consistência
            if ($user->empresa_ativa_id !== $empresa->id) {
                $user->empresa_ativa_id = $empresa->id;
                $user->save();
            }
            
            \Log::info('BaseApiController::getEmpresaAtiva - Empresa obtida do relacionamento', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'empresa_id' => $empresa->id,
                'empresa_razao_social' => $empresa->razao_social,
            ]);
        } else {
            \Log::warning('BaseApiController::getEmpresaAtiva - Usuário sem empresa associada', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);
        }

        return $empresa;
    }

    /**
     * Obtém a empresa do usuário ou retorna erro 403
     * Cada usuário deve ter exatamente UMA empresa associada
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        $empresa = $this->getEmpresaAtiva();
        
        if (!$empresa) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail - Usuário sem empresa associada', [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
            ]);
            abort(403, 'Você não possui uma empresa associada. Entre em contato com o administrador.');
        }

        return $empresa;
    }
}

