<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Application\Auth\UseCases\SwitchEmpresaAtivaUseCase;
use App\Domain\Shared\ValueObjects\TenantContext;

/**
 * Controller para seleção de empresa (Web)
 * 
 * ⚠️ TEMPORÁRIO: Este controller é um stub para manter compatibilidade com rotas web.
 * Idealmente, essa funcionalidade deveria ser movida para o frontend React.
 */
class EmpresaSelecaoController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private SwitchEmpresaAtivaUseCase $switchEmpresaAtivaUseCase,
    ) {}

    /**
     * Exibir página de seleção de empresa
     */
    public function selecionar(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            // Se for API, retornar JSON. Se for web, redirecionar para /login
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Usuário não autenticado.'], 401);
            }
            return redirect('/login');
        }

        // Listar empresas do tenant atual
        $empresasDomain = $this->empresaRepository->listar();

        // Converter entidades de domínio para arrays para a view
        $empresas = array_map(function ($empresa) {
            return (object) [
                'id' => $empresa->id,
                'razao_social' => $empresa->razaoSocial,
                'cnpj' => $empresa->cnpj,
                'cidade' => $empresa->cidade,
                'estado' => $empresa->estado,
            ];
        }, $empresasDomain);

        return view('empresas.selecionar', [
            'empresas' => $empresas,
        ]);
    }

    /**
     * Definir empresa ativa
     */
    public function definir(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            // Se for API, retornar JSON. Se for web, redirecionar para /login
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Usuário não autenticado.'], 401);
            }
            return redirect('/login');
        }

        try {
            $novaEmpresaId = $request->input('empresa_id');
            $context = TenantContext::create(tenancy()->tenant?->id ?? 0);

            // Executar use case
            $this->switchEmpresaAtivaUseCase->executar($user->id, $novaEmpresaId, $context);

            // Se for API, retornar JSON. Se for web, redirecionar
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Empresa selecionada com sucesso!']);
            }
            return redirect('/dashboard')->with('success', 'Empresa selecionada com sucesso!');
        } catch (\Exception $e) {
            // Se for API, retornar JSON. Se for web, redirecionar
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Erro ao selecionar empresa: ' . $e->getMessage()], 400);
            }
            return redirect('/empresas/selecionar')
                ->with('error', 'Erro ao selecionar empresa: ' . $e->getMessage());
        }
    }
}

