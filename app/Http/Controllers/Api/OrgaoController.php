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
        
        // Log da query SQL que será executada
        \Log::info('OrgaoController::index - Query SQL', [
            'empresa_id' => $empresa->id,
            'empresa_razao_social' => $empresa->razao_social,
            'query_sql' => Orgao::where('empresa_id', $empresa->id)
                ->whereNotNull('empresa_id')
                ->toSql(),
        ]);
        
        // Filtrar APENAS órgãos da empresa ativa (não incluir NULL)
        // Também filtrar setores por empresa_id para garantir isolamento
        $query = Orgao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->with(['setors' => function($query) use ($empresa) {
                $query->where('empresa_id', $empresa->id)
                      ->whereNotNull('empresa_id');
            }]);
        
        // Log: Verificar quantos órgãos existem no total e quantos pertencem à empresa
        $totalOrgaos = Orgao::count();
        $totalOrgaosEmpresa = Orgao::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->count();
        $totalOrgaosNull = Orgao::whereNull('empresa_id')->count();
        $totalOrgaosOutrasEmpresas = Orgao::whereNotNull('empresa_id')->where('empresa_id', '!=', $empresa->id)->count();
        
        \Log::info('OrgaoController::index - Estatísticas', [
            'total_orgaos_banco' => $totalOrgaos,
            'total_orgaos_empresa_ativa' => $totalOrgaosEmpresa,
            'total_orgaos_null' => $totalOrgaosNull,
            'total_orgaos_outras_empresas' => $totalOrgaosOutrasEmpresas,
            'empresa_id' => $empresa->id,
        ]);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('razao_social', 'like', "%{$request->search}%")
                  ->orWhere('cnpj', 'like', "%{$request->search}%");
            });
        }

        // Executar query e obter resultados BRUTOS (sem paginação) para verificar
        $orgaosBrutos = $query->orderBy('razao_social')->get();
        
        \Log::info('OrgaoController::index - Resultados BRUTOS da Query', [
            'total_bruto' => $orgaosBrutos->count(),
            'orgaos_brutos' => $orgaosBrutos->map(function($orgao) use ($empresa) {
                return [
                    'id' => $orgao->id,
                    'razao_social' => $orgao->razao_social,
                    'empresa_id' => $orgao->empresa_id,
                    'empresa_id_esperado' => $empresa->id,
                    'pertence_empresa' => $orgao->empresa_id === $empresa->id,
                ];
            })->toArray(),
        ]);
        
        // Filtrar novamente ANTES da paginação para garantir isolamento
        $orgaosFiltradosAntes = $orgaosBrutos->filter(function($orgao) use ($empresa) {
            $pertence = $orgao->empresa_id === $empresa->id && $orgao->empresa_id !== null;
            
            if (!$pertence) {
                \Log::warning('OrgaoController::index - Órgão removido ANTES da paginação!', [
                    'orgao_id' => $orgao->id,
                    'orgao_razao_social' => $orgao->razao_social,
                    'orgao_empresa_id' => $orgao->empresa_id,
                    'empresa_ativa_id' => $empresa->id,
                ]);
            }
            
            return $pertence;
        });
        
        // Criar paginação manual com os dados filtrados
        $page = $request->get('page', 1);
        $perPage = 15;
        $offset = ($page - 1) * $perPage;
        $orgaosPaginated = $orgaosFiltradosAntes->slice($offset, $perPage)->values();
        
        // Criar objeto de paginação manual
        $orgaos = new \Illuminate\Pagination\LengthAwarePaginator(
            $orgaosPaginated,
            $orgaosFiltradosAntes->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Log dos resultados finais
        \Log::info('OrgaoController::index - Resultados Finais', [
            'total_orgaos_bruto' => $orgaosBrutos->count(),
            'total_orgaos_filtrado_antes_paginacao' => $orgaosFiltradosAntes->count(),
            'total_orgaos_paginado' => $orgaosPaginated->count(),
            'empresa_id_filtro' => $empresa->id,
            'empresa_razao_social' => $empresa->razao_social,
            'orgaos_finais' => $orgaosPaginated->map(function($orgao) {
                return [
                    'id' => $orgao->id,
                    'razao_social' => $orgao->razao_social,
                    'empresa_id' => $orgao->empresa_id,
                ];
            })->toArray(),
        ]);
        
        // Verificação FINAL: Garantir que NENHUM órgão inválido seja retornado
        $orgaosFinais = $orgaosPaginated->filter(function($orgao) use ($empresa) {
            $pertence = $orgao->empresa_id === $empresa->id && $orgao->empresa_id !== null;
            
            if (!$pertence) {
                \Log::error('OrgaoController::index - ERRO CRÍTICO: Órgão inválido na paginação!', [
                    'orgao_id' => $orgao->id,
                    'orgao_razao_social' => $orgao->razao_social,
                    'orgao_empresa_id' => $orgao->empresa_id,
                    'empresa_ativa_id' => $empresa->id,
                ]);
            }
            
            return $pertence;
        });
        
        // Atualizar collection da paginação com dados filtrados FINAIS
        $orgaos->setCollection($orgaosFinais);

        // Criar resposta com informações de debug
        $response = OrgaoResource::collection($orgaos);
        
        // Adicionar metadata de debug na resposta (sempre, para facilitar debug)
        $response->additional([
            'meta' => [
                'empresa_filtro' => [
                    'id' => $empresa->id,
                    'razao_social' => $empresa->razao_social,
                ],
                'usuario' => [
                    'id' => auth()->id(),
                    'email' => auth()->user()?->email,
                    'empresa_ativa_id' => auth()->user()?->empresa_ativa_id,
                ],
                'estatisticas' => [
                    'total_orgaos_banco' => $totalOrgaos,
                    'total_orgaos_empresa_ativa' => $totalOrgaosEmpresa,
                    'total_orgaos_null' => $totalOrgaosNull,
                    'total_orgaos_outras_empresas' => $totalOrgaosOutrasEmpresas,
                    'total_retornado_query_bruto' => $orgaosBrutos->count(),
                    'total_retornado_antes_paginacao' => $orgaosFiltradosAntes->count(),
                    'total_retornado_final' => $orgaosFinais->count(),
                ],
                'query' => [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                ],
                'orgaos_retornados' => $orgaosFinais->map(function($orgao) use ($empresa) {
                    return [
                        'id' => $orgao->id,
                        'razao_social' => $orgao->razao_social,
                        'empresa_id' => $orgao->empresa_id,
                        'empresa_id_esperado' => $empresa->id,
                        'pertence_empresa' => $orgao->empresa_id === $empresa->id,
                    ];
                })->toArray(),
            ],
        ]);

        return $response;
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







