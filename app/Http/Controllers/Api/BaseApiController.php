<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;

/**
 * Controller base para APIs que precisam de empresa ativa
 * 
 * Fornece métodos auxiliares comuns para controllers de API
 */
abstract class BaseApiController extends Controller
{
    /**
     * Obtém a empresa ativa do usuário autenticado ou lança exceção
     * 
     * @return Empresa
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Usuário não autenticado.');
        }
        
        \Log::debug('BaseApiController::getEmpresaAtivaOrFail()', [
            'user_id' => $user->id,
            'user_empresa_ativa_id' => $user->empresa_ativa_id ?? null,
            'tenant_id' => tenancy()->tenant?->id,
        ]);
        
        // Se o usuário tem empresa_ativa_id, buscar essa empresa
        if ($user->empresa_ativa_id) {
            $empresa = Empresa::find($user->empresa_ativa_id);
            if ($empresa) {
                \Log::debug('BaseApiController::getEmpresaAtivaOrFail() - Empresa encontrada por empresa_ativa_id', [
                    'empresa_id' => $empresa->id,
                    'empresa_razao_social' => $empresa->razao_social,
                ]);
                return $empresa;
            } else {
                \Log::warning('BaseApiController::getEmpresaAtivaOrFail() - Empresa não encontrada pelo empresa_ativa_id', [
                    'empresa_ativa_id' => $user->empresa_ativa_id,
                ]);
            }
        }
        
        // Se não tem empresa ativa definida, buscar primeira empresa do usuário
        $empresa = $user->empresas()->first();
        
        if (!$empresa) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail() - Usuário sem empresas', [
                'user_id' => $user->id,
            ]);
            abort(403, 'Você não tem acesso a nenhuma empresa.');
        }
        
        // Se encontrou empresa mas não estava definida como ativa, atualizar
        if ($user->empresa_ativa_id !== $empresa->id) {
            \Log::info('BaseApiController::getEmpresaAtivaOrFail() - Atualizando empresa_ativa_id', [
                'user_id' => $user->id,
                'empresa_ativa_id_antigo' => $user->empresa_ativa_id,
                'empresa_ativa_id_novo' => $empresa->id,
            ]);
            $user->empresa_ativa_id = $empresa->id;
            $user->save();
        }
        
        \Log::debug('BaseApiController::getEmpresaAtivaOrFail() - Retornando empresa', [
            'empresa_id' => $empresa->id,
            'empresa_razao_social' => $empresa->razao_social,
        ]);
        
        return $empresa;
    }
}

