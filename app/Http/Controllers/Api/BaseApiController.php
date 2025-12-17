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
        
        // Log para debug
        \Log::info('BaseApiController::getEmpresaAtiva - Debug', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'empresa_ativa_id' => $user?->empresa_ativa_id,
            'tenant_id' => tenancy()->tenant?->id,
        ]);
        
        if (!$user || !$user->empresa_ativa_id) {
            \Log::warning('BaseApiController::getEmpresaAtiva - Usuário sem empresa_ativa_id', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'empresa_ativa_id' => $user?->empresa_ativa_id,
            ]);
            return null;
        }

        $empresa = Empresa::find($user->empresa_ativa_id);
        
        if (!$empresa) {
            \Log::error('BaseApiController::getEmpresaAtiva - Empresa não encontrada', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'empresa_ativa_id' => $user->empresa_ativa_id,
                'empresas_disponiveis' => Empresa::pluck('id', 'razao_social')->toArray(),
            ]);
        } else {
            \Log::info('BaseApiController::getEmpresaAtiva - Empresa encontrada', [
                'empresa_id' => $empresa->id,
                'empresa_razao_social' => $empresa->razao_social,
            ]);
        }

        return $empresa;
    }

    /**
     * Obtém a empresa ativa ou retorna erro 403
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        $empresa = $this->getEmpresaAtiva();
        
        if (!$empresa) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail - Abortando com 403', [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'empresa_ativa_id' => auth()->user()?->empresa_ativa_id,
            ]);
            abort(403, 'Nenhuma empresa ativa selecionada. Selecione uma empresa antes de continuar.');
        }

        return $empresa;
    }
}

