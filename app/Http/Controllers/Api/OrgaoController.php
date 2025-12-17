<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\OrgaoResource;
use App\Models\Orgao;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;

class OrgaoController extends BaseApiController
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Log para debug
        \Log::info('OrgaoController::index - Debug', [
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'empresa_ativa_id' => auth()->user()?->empresa_ativa_id,
            'empresa_id' => $empresa->id,
            'empresa_razao_social' => $empresa->razao_social,
            'tenant_id' => tenancy()->tenant?->id,
        ]);
        
        // Filtrar APENAS órgãos da empresa ativa (não incluir NULL)
        // Também filtrar setores por empresa_id para garantir isolamento
        $query = Orgao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->with(['setors' => function($query) use ($empresa) {
                $query->where('empresa_id', $empresa->id)
                      ->whereNotNull('empresa_id');
            }]);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('razao_social', 'like', "%{$request->search}%")
                  ->orWhere('cnpj', 'like', "%{$request->search}%");
            });
        }

        // Executar query e obter resultados
        $orgaos = $query->orderBy('razao_social')->paginate(15);
        
        // Verificação CRÍTICA: Filtrar novamente após paginação para garantir isolamento
        // Isso garante que mesmo se houver algum problema na query, os dados serão filtrados
        $orgaosCollection = $orgaos->getCollection();
        $orgaosFiltrados = $orgaosCollection->filter(function($orgao) use ($empresa) {
            return $orgao->empresa_id === $empresa->id && $orgao->empresa_id !== null;
        });
        
        // Log dos resultados com mais detalhes
        \Log::info('OrgaoController::index - Resultados', [
            'total_orgaos_antes_filtro' => $orgaosCollection->count(),
            'total_orgaos_depois_filtro' => $orgaosFiltrados->count(),
            'empresa_id_filtro' => $empresa->id,
            'empresa_razao_social' => $empresa->razao_social,
            'orgaos_empresa_ids' => $orgaosFiltrados->pluck('empresa_id')->unique()->toArray(),
            'orgaos_detalhes' => $orgaosFiltrados->map(function($orgao) {
                return [
                    'id' => $orgao->id,
                    'razao_social' => $orgao->razao_social,
                    'empresa_id' => $orgao->empresa_id,
                ];
            })->toArray(),
        ]);
        
        // Verificação adicional: garantir que todos os órgãos retornados pertencem à empresa
        $orgaosInvalidos = $orgaosFiltrados->filter(function($orgao) use ($empresa) {
            return $orgao->empresa_id !== $empresa->id || $orgao->empresa_id === null;
        });
        
        if ($orgaosInvalidos->count() > 0) {
            \Log::error('OrgaoController::index - Órgãos com empresa_id incorreto encontrados APÓS FILTRO!', [
                'empresa_id_esperado' => $empresa->id,
                'orgaos_invalidos' => $orgaosInvalidos->map(function($orgao) {
                    return [
                        'id' => $orgao->id,
                        'razao_social' => $orgao->razao_social,
                        'empresa_id' => $orgao->empresa_id,
                    ];
                })->toArray(),
            ]);
            
            // Remover órgãos inválidos
            $orgaosFiltrados = $orgaosFiltrados->reject(function($orgao) use ($empresa) {
                return $orgao->empresa_id !== $empresa->id || $orgao->empresa_id === null;
            });
        }
        
        // Atualizar collection da paginação com dados filtrados
        $orgaos->setCollection($orgaosFiltrados);

        return OrgaoResource::collection($orgaos);
    }

    public function store(Request $request)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar órgãos.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();

        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'telefones' => 'nullable|array',
            'telefones.*' => 'string|max:20',
            'emails' => 'nullable|array',
            'emails.*' => 'email|max:255',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $validated['empresa_id'] = $empresa->id;
        $orgao = Orgao::create($validated);
        $orgao->load('setors');

        return new OrgaoResource($orgao);
    }

    public function show(Orgao $orgao)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($orgao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $orgao->load('setors');
        return new OrgaoResource($orgao);
    }

    public function update(Request $request, Orgao $orgao)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar órgãos.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($orgao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        $validated = $request->validate([
            'uasg' => 'nullable|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'telefones' => 'nullable|array',
            'telefones.*' => 'string|max:20',
            'emails' => 'nullable|array',
            'emails.*' => 'email|max:255',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $orgao->update($validated);
        $orgao->load('setors');

        return new OrgaoResource($orgao);
    }

    public function destroy(Orgao $orgao)
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir órgãos.',
            ], 403);
        }

        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($orgao->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }

        if ($orgao->processos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um órgão que possui processos vinculados.'
            ], 403);
        }

        $orgao->forceDelete();

        return response()->json(null, 204);
    }
}




