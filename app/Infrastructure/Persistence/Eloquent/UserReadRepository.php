<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserReadRepository implements UserReadRepositoryInterface
{
    public function buscarComRelacionamentos(int $userId): ?array
    {
        $this->checkTenancyContext();
        
        try {
            $user = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->find($userId);
            return $user ? $this->mapUserToArray($user) : null;
        } catch (\Exception $e) {
            Log::error("Erro ao buscar usuário por ID: " . $e->getMessage(), [
                'user_id' => $userId,
                'tenant_id' => tenancy()->tenant?->id,
            ]);
            return null;
        }
    }

    public function buscarPorEmail(string $email): ?array
    {
        $this->checkTenancyContext();
        
        try {
            $user = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->where('email', $email)
                ->first();
            return $user ? $this->mapUserToArray($user) : null;
        } catch (\Exception $e) {
            Log::error("Erro ao buscar usuário por email: " . $e->getMessage(), [
                'email' => $email,
                'tenant_id' => tenancy()->tenant?->id,
            ]);
            return null;
        }
    }

    public function listarComRelacionamentos(array $filtros = []): LengthAwarePaginator
    {
        $this->checkTenancyContext();

        try {
            $query = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                    $search = $filtros['search'];
                    $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")
                                              ->orWhere('email', 'like', "%{$search}%"));
                })
                ->when(!empty($filtros['empresa_id']), function ($q) use ($filtros) {
                    $q->whereHas('empresas', fn($e) => $e->where('empresas.id', $filtros['empresa_id']));
                });

            $paginator = $query->orderBy('name')->paginate($filtros['per_page'] ?? 15);

            // Transforma os itens usando o método map que já criamos
            $paginator->setCollection(
                $paginator->getCollection()->map(fn($user) => $this->mapUserToArray($user))
            );

            return $paginator;

        } catch (\Exception $e) {
            Log::error("Erro ao listar usuários: " . $e->getMessage(), [
                'tenant_id' => tenancy()->tenant?->id,
                'filtros' => $filtros,
            ]);
            return $this->createEmptyPaginator($filtros);
        }
    }

    public function listarSemPaginacao(array $filtros = []): array
    {
        $this->checkTenancyContext();
        
        $tenantId = tenancy()->tenant?->id;
        $databaseName = DB::connection()->getDatabaseName();
        
        Log::info('UserReadRepository::listarSemPaginacao - Iniciando', [
            'tenant_id' => $tenantId,
            'database' => $databaseName,
            'filtros' => $filtros,
        ]);

        try {
            $query = $this->getIsolatedUserQuery()
                ->with(['empresas', 'roles'])
                ->when(!empty($filtros['search']), function ($q) use ($filtros) {
                    $search = $filtros['search'];
                    $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")
                                              ->orWhere('email', 'like', "%{$search}%"));
                })
                ->when(!empty($filtros['empresa_id']), function ($q) use ($filtros) {
                    $q->whereHas('empresas', fn($e) => $e->where('empresas.id', $filtros['empresa_id']));
                });

            $users = $query->orderBy('name')->get();
            
            // 🔥 DEBUG: Log detalhado dos usuários encontrados no banco
            $usuariosDetalhes = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'empresa_ativa_id' => $user->empresa_ativa_id,
                    'deleted_at' => $user->deleted_at?->toDateTimeString(),
                    'empresas_ids' => $user->empresas->pluck('id')->toArray(),
                    'empresas_razao_social' => $user->empresas->pluck('razao_social')->toArray(),
                    'roles' => $user->roles->pluck('name')->toArray(),
                ];
            })->toArray();
            
            Log::info('UserReadRepository::listarSemPaginacao - Usuários encontrados no banco', [
                'total_usuarios' => $users->count(),
                'tenant_id' => $tenantId,
                'database' => $databaseName,
                'usuarios_detalhes' => $usuariosDetalhes,
            ]);

            // Transforma os itens usando o método map que já criamos
            $result = $users->map(fn($user) => $this->mapUserToArray($user))->toArray();
            
            // 🔥 DEBUG: Log detalhado dos usuários após transformação
            $usuariosTransformados = array_map(function ($userArray) {
                return [
                    'id' => $userArray['id'] ?? null,
                    'name' => $userArray['name'] ?? null,
                    'email' => $userArray['email'] ?? null,
                    'empresa_ativa_id' => $userArray['empresa_ativa_id'] ?? null,
                    'total_empresas' => $userArray['total_empresas'] ?? 0,
                    'empresas_ids' => array_column($userArray['empresas'] ?? [], 'id'),
                    'roles' => $userArray['roles'] ?? [],
                ];
            }, $result);
            
            Log::info('UserReadRepository::listarSemPaginacao - Concluído (após transformação)', [
                'total_resultados' => count($result),
                'tenant_id' => $tenantId,
                'usuarios_transformados' => $usuariosTransformados,
            ]);
            
            return $result;

        } catch (\Exception $e) {
            Log::error("Erro ao listar usuários sem paginação: " . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'database' => $databaseName,
                'filtros' => $filtros,
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Centraliza a transformação do Model para o Array de saída (Frontend)
     */
    private function mapUserToArray(UserModel $user): array
    {
        $empresas = $user->empresas->map(fn($e) => [
            'id' => $e->id,
            'razao_social' => $e->razao_social,
        ])->toArray();

        $roles = $user->roles->pluck('name')->toArray();
        $totalEmpresas = count($empresas);
        
        // ✅ CORREÇÃO: Garantir que empresa_ativa seja válida e esteja vinculada ao usuário
        $empresaAtiva = null;
        if ($user->empresa_ativa_id) {
            // Tentar encontrar no relacionamento carregado primeiro
            $modelAtiva = $user->empresas->firstWhere('id', $user->empresa_ativa_id);
            
            if ($modelAtiva) {
                // Empresa encontrada no relacionamento - usar ela
                $empresaAtiva = [
                    'id' => $modelAtiva->id,
                    'razao_social' => $modelAtiva->razao_social,
                ];
            } else {
                // Empresa não está no relacionamento - pode ser inconsistência
                // Verificar se a empresa existe e está vinculada ao usuário
                $empresaNoRelacionamento = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->first();
                
                if ($empresaNoRelacionamento) {
                    // Empresa existe mas não estava no relacionamento carregado - recarregar
                    Log::warning('UserReadRepository::mapUserToArray - Empresa ativa não estava no relacionamento carregado', [
                        'user_id' => $user->id,
                        'empresa_ativa_id' => $user->empresa_ativa_id,
                        'empresas_ids_no_relacionamento' => $user->empresas->pluck('id')->toArray(),
                    ]);
                    
                    $empresaAtiva = [
                        'id' => $empresaNoRelacionamento->id,
                        'razao_social' => $empresaNoRelacionamento->razao_social,
                    ];
                } else {
                    // ✅ INCONSISTÊNCIA: empresa_ativa_id não está vinculada ao usuário
                    // Limpar empresa_ativa_id e usar a primeira empresa válida
                    Log::error('UserReadRepository::mapUserToArray - INCONSISTÊNCIA: empresa_ativa_id não está vinculada ao usuário', [
                        'user_id' => $user->id,
                        'empresa_ativa_id_invalida' => $user->empresa_ativa_id,
                        'empresas_ids_validas' => $user->empresas->pluck('id')->toArray(),
                    ]);
                    
                    // Usar primeira empresa válida se existir
                    $primeiraEmpresa = $user->empresas->first();
                    if ($primeiraEmpresa) {
                        // Atualizar empresa_ativa_id para a primeira empresa válida
                        $empresaAtivaIdAntiga = $user->empresa_ativa_id;
                        $user->empresa_ativa_id = $primeiraEmpresa->id;
                        $user->save();
                        
                        Log::info('UserReadRepository::mapUserToArray - empresa_ativa_id corrigida automaticamente', [
                            'user_id' => $user->id,
                            'empresa_ativa_id_antiga' => $empresaAtivaIdAntiga,
                            'empresa_ativa_id_nova' => $primeiraEmpresa->id,
                        ]);
                        
                        $empresaAtiva = [
                            'id' => $primeiraEmpresa->id,
                            'razao_social' => $primeiraEmpresa->razao_social,
                        ];
                    }
                }
            }
        } else if (!empty($empresas)) {
            // Se não tem empresa_ativa_id mas tem empresas, usar a primeira
            $primeiraEmpresa = $user->empresas->first();
            if ($primeiraEmpresa) {
                // Atualizar empresa_ativa_id
                $user->empresa_ativa_id = $primeiraEmpresa->id;
                $user->save();
                
                $empresaAtiva = [
                    'id' => $primeiraEmpresa->id,
                    'razao_social' => $primeiraEmpresa->razao_social,
                ];
            }
        }

        // ✅ Garantir que empresa_ativa_id está sincronizado após possíveis correções
        $empresaAtivaIdFinal = $empresaAtiva ? $empresaAtiva['id'] : ($user->empresa_ativa_id ?? null);
        
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'empresa_ativa_id' => $empresaAtivaIdFinal,
            'empresa_ativa' => $empresaAtiva,
            'roles' => $roles,
            'roles_list' => $roles,
            'empresas' => $empresas,
            'empresas_list' => $empresas,
            'total_empresas' => $totalEmpresas,
            'is_multi_empresa' => $totalEmpresas > 1,
            'deleted_at' => ($user->trashed() && ($deletedAt = $user->getAttribute($user->getDeletedAtColumn()))) 
                ? $deletedAt->toISOString() 
                : null,
        ];
    }

    /**
     * Centraliza a query de usuários com isolamento de tenant
     * 
     * 🔥 CORREÇÃO: Garante que a query use a conexão 'tenant' quando o tenancy estiver inicializado
     * e o modelo User já está configurado para usar a conexão correta via getConnectionName()
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getIsolatedUserQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = tenancy()->tenant?->id;
        $databaseName = DB::connection()->getDatabaseName();
        
        Log::debug('UserReadRepository::getIsolatedUserQuery - Contexto', [
            'tenant_id' => $tenantId,
            'database_name' => $databaseName,
            'default_connection' => config('database.default'),
            'tenancy_initialized' => tenancy()->initialized,
        ]);
        
        // 🔥 CORREÇÃO: Usar UserModel que já tem getConnectionName() configurado
        // O modelo User automaticamente usa a conexão 'tenant' quando o tenancy está inicializado
        $query = UserModel::withTrashed();
        
        // Se estivermos no banco tenant, a query já está isolada naturalmente
        // Se estivermos no banco central (fallback), o Global Scope do User já aplica o filtro
        // Não precisamos aplicar filtros adicionais aqui pois o modelo já gerencia a conexão
        
        return $query;
    }
    
    /**
     * Cria um paginador vazio quando não há dados disponíveis
     */
    private function createEmptyPaginator(array $filtros): LengthAwarePaginator
    {
        $perPage = $filtros['per_page'] ?? 15;
        $currentPage = request()->get('page', 1);
        
        return new Paginator(
            [],
            0,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Valida se o contexto do tenancy está inicializado
     */
    private function checkTenancyContext(): void
    {
        if (!tenancy()->initialized) {
            Log::error('UserReadRepository: Acesso tentado sem inicializar Tenancy.');
            throw new \RuntimeException('Contexto de Tenant não identificado.');
        }
    }
}

