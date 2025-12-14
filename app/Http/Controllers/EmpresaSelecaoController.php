<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Empresa;

class EmpresaSelecaoController extends Controller
{
    public function selecionar()
    {
        $user = auth()->user();
        $empresas = $user->empresas;

        return view('empresas.selecionar', compact('empresas'));
    }

    public function definir(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
        ]);

        $user = auth()->user();
        
        // Verifica se o usuário tem acesso à empresa
        if (!$user->empresas->contains($request->empresa_id)) {
            abort(403, 'Você não tem acesso a esta empresa.');
        }

        $user->empresa_ativa_id = $request->empresa_id;
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Empresa selecionada com sucesso!');
    }
}
