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
        
        // Se o usuário tem empresa_ativa_id, buscar essa empresa
        if ($user->empresa_ativa_id) {
            $empresa = Empresa::find($user->empresa_ativa_id);
            if ($empresa) {
                return $empresa;
            }
        }
        
        // Se não tem empresa ativa definida, buscar primeira empresa do usuário
        $empresa = $user->empresas()->first();
        
        if (!$empresa) {
            abort(403, 'Você não tem acesso a nenhuma empresa.');
        }
        
        // Se encontrou empresa mas não estava definida como ativa, atualizar
        if ($user->empresa_ativa_id !== $empresa->id) {
            $user->empresa_ativa_id = $empresa->id;
            $user->save();
        }
        
        return $empresa;
    }
}

