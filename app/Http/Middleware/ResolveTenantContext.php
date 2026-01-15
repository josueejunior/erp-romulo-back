<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 CAMADA 5 - Tenancy
 * 
 * Responsabilidade ÚNICA: Resolver tenant e inicializar tenancy
 * 
 * ✅ Faz:
 * - Resolve tenant (header / rota / payload JWT)
 * - Inicializa tenancy: tenancy()->initialize($tenant)
 * 
 * ❌ NUNCA faz:
 * - Autenticação (já foi feita por AuthenticateJWT)
 * - Validação de regras de negócio
 * 
 * 🔥 IMPORTANTE: Rotas auth.* são ISENTAS de tenant obrigatório
 */
class ResolveTenantContext
{
    // Sem dependências no construtor para evitar problemas de binding

    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('➡ ResolveTenantContext entrou', ['path' => $request->path()]);

        // 🔥 CRÍTICO: Se não há rota resolvida, pular middleware
        if (!$request->route()) {
            Log::debug('⬅ ResolveTenantContext: sem rota, pulando');
            return $next($request);
        }

        // 🔥 CRÍTICO: Rotas de autenticação NÃO exigem tenant
        // O frontend precisa chamar essas rotas ANTES de saber o tenant
        if ($this->isExemptRoute($request)) {
            Log::debug('⬅ ResolveTenantContext: rota isenta', ['route' => $request->route()->getName()]);
            return $next($request);
        }

        // Verificar se usuário está autenticado
        $user = auth('sanctum')->user();
        
        if (!$user) {
            Log::warning('ResolveTenantContext: Usuário não autenticado');
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
            ], 401);
        }

        // Se for admin, não precisa de tenant
        if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            Log::debug('⬅ ResolveTenantContext: admin detectado');
            return $next($request);
        }

        // 🔥 ARQUITETURA RIGOROSA: Hierarquia da Verdade
        // REGRA DE OURO: O Token JWT é a autoridade máxima
        $headerTenantId = $request->header('X-Tenant-ID') ? (int) $request->header('X-Tenant-ID') : null;
        $tokenTenantId = null;
        
        // Extrair tenant_id do JWT (já injetado por AuthenticateJWT)
        if ($request->attributes->has('auth')) {
            $payload = $request->attributes->get('auth');
            $tokenTenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
        }
        
        // 🔥 VALIDAÇÃO RIGOROSA: Se header e token divergem, frontend está enviando cache antigo
        // REGRA DE OURO: O JWT é a autoridade máxima. Se divergir, barrar imediatamente.
        if ($headerTenantId && $tokenTenantId && $headerTenantId !== $tokenTenantId) {
            Log::error('ResolveTenantContext: ❌ Tenant Context Mismatch - BLOQUEANDO REQUISIÇÃO', [
                'user_id' => $user->id,
                'user_email' => $user->email ?? 'N/A',
                'header_tenant_id' => $headerTenantId,
                'token_tenant_id' => $tokenTenantId,
                'url' => $request->fullUrl(),
                'problema' => 'Frontend está enviando tenant_id diferente do JWT (sessionStorage corrompido)',
                'solucao' => 'Frontend deve usar tenant_id do JWT decodificado ou fazer novo login',
            ]);
            
            return response()->json([
                'error' => 'Tenant Context Mismatch',
                'message' => 'O tenant_id do header não corresponde ao token. Faça login novamente.',
                'code' => 'TENANT_MISMATCH',
                'correct_tenant_id' => $tokenTenantId, // Informar qual é o correto
            ], 403);
        }
        
        // 🔥 REGRA DE OURO: Prioridade ABSOLUTA ao Token JWT (fonte de verdade)
        // Se o token tem tenant_id, usar APENAS ele. Ignorar header se divergir.
        // Se o token não tem tenant_id mas o header tem, usar header (caso legado/admin).
        if ($tokenTenantId) {
            // Token tem tenant_id → usar APENAS ele (ignorar header se divergir)
            $tenantId = $tokenTenantId;
            
            // Se header existe e diverge, logar warning (mas já foi bloqueado acima)
            if ($headerTenantId && $headerTenantId !== $tokenTenantId) {
                // Já foi bloqueado acima, mas logar para auditoria
                Log::warning('ResolveTenantContext: Header divergente ignorado (usando JWT)', [
                    'header_tenant_id' => $headerTenantId,
                    'token_tenant_id' => $tokenTenantId,
                ]);
            }
        } else {
            // Token não tem tenant_id → usar header (fallback para admin/legado)
            $tenantId = $headerTenantId;
        }
        
        Log::info('ResolveTenantContext: Tenant ID resolvido', [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'user_email' => $user->email ?? 'N/A',
            'source' => $tokenTenantId ? 'jwt_token' : ($headerTenantId ? 'header' : 'none'),
            'header_tenant_id' => $headerTenantId,
            'token_tenant_id' => $tokenTenantId,
        ]);
        
        if (!$tenantId) {
            Log::warning('ResolveTenantContext: Tenant não identificado', [
                'user_id' => $user->id,
                'user_email' => $user->email ?? 'N/A',
                'headers' => [
                    'X-Tenant-ID' => $request->header('X-Tenant-ID'),
                ],
                'jwt_payload' => $request->attributes->has('auth') ? $request->attributes->get('auth') : null,
            ]);
            return response()->json([
                'message' => 'Tenant não identificado. Envie o header X-Tenant-ID.',
            ], 400);
        }

        // Inicializar tenancy
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            Log::warning('ResolveTenantContext: Tenant não encontrado', [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'message' => 'Tenant não encontrado.',
            ], 404);
        }

        // 🔥 SEGURANÇA: Validar que o usuário pertence ao tenant (prevenir Tenant Hopping)
        $validacao = $this->validarRelacaoUsuarioTenant($user, $tenantId);
        
        if (!$validacao['valido']) {
            // Se encontrou o tenant correto, retornar resposta especial
            if (isset($validacao['tenant_correto'])) {
                return response()->json([
                    'error' => 'Invalid Tenant Relation',
                    'message' => 'Tenant incorreto. Use o tenant ' . $validacao['tenant_correto'] . ' no header X-Tenant-ID.',
                    'correct_tenant_id' => $validacao['tenant_correto'],
                    'code' => 'WRONG_TENANT',
                ], 403);
            }
            
            // Caso contrário, retornar erro genérico
            return response()->json([
                'error' => 'Invalid Tenant Relation',
                'message' => 'Acesso não autorizado a este tenant.',
                'code' => 'INVALID_TENANT_RELATION',
            ], 403);
        }

        tenancy()->initialize($tenant);
        
        Log::debug('⬅ ResolveTenantContext: tenancy inicializado', ['tenant_id' => $tenantId]);

        return $next($request);
    }

    /**
     * Verificar se a rota é isenta de tenant obrigatório
     */
    private function isExemptRoute(Request $request): bool
    {
        $routeName = $request->route()->getName();
        
        // Rotas isentas por nome
        $exemptPatterns = [
            'auth.*',           // Login, logout, refresh, etc
            'login',
            'logout',
            'register',
            'password.*',       // Reset de senha
            'verification.*',   // Verificação de email
        ];

        foreach ($exemptPatterns as $pattern) {
            if ($routeName && fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        // Rotas isentas por path
        $exemptPaths = [
            'api/v1/auth/*',
            'api/auth/*',
            'auth/*',
            'api/v1/onboarding/*',  // 🔥 Onboarding não precisa de validação rigorosa de tenant (pode ter múltiplos tenants)
        ];

        $path = $request->path();
        foreach ($exemptPaths as $exemptPath) {
            if (fnmatch($exemptPath, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolver tenant_id de múltiplas fontes (prioridade)
     */
    private function resolveTenantId(Request $request): ?int
    {
        // Prioridade 1: Header X-Tenant-ID
        if ($request->header('X-Tenant-ID')) {
            return (int) $request->header('X-Tenant-ID');
        }

        // Prioridade 2: Payload JWT (já injetado por AuthenticateJWT)
        if ($request->attributes->has('auth')) {
            $payload = $request->attributes->get('auth');
            if (isset($payload['tenant_id'])) {
                return (int) $payload['tenant_id'];
            }
        }

        // Prioridade 3: Parâmetro da rota
        if ($request->route('tenant')) {
            $tenant = $request->route('tenant');
            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }
        }

        return null;
    }

    /**
     * Obter a fonte do tenant_id para logs
     */
    private function getTenantIdSource(Request $request): string
    {
        if ($request->header('X-Tenant-ID')) {
            return 'header_X-Tenant-ID';
        }

        if ($request->attributes->has('auth')) {
            $payload = $request->attributes->get('auth');
            if (isset($payload['tenant_id'])) {
                return 'jwt_payload';
            }
        }

        if ($request->route('tenant')) {
            return 'route_parameter';
        }

        return 'not_found';
    }

    /**
     * 🔥 SEGURANÇA: Validar que o usuário realmente pertence ao tenant
     * 
     * Previne Tenant Hopping: usuário mal-intencionado não pode manipular JWT
     * para acessar dados de outros tenants.
     * 
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param int $tenantId
     * @return array ['valido' => bool, 'tenant_correto' => int|null]
     */
    private function validarRelacaoUsuarioTenant($user, int $tenantId): array
    {
        // Admin não precisa de validação (tem acesso a todos os tenants)
        if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
            Log::debug('ResolveTenantContext: Usuário é admin, validação bypassada', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
            ]);
            return ['valido' => true];
        }

        // Buscar na users_lookup para validar relação
        try {
            $lookupRepository = app(\App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface::class);
            
            // Buscar todos os registros do usuário por email (ativos e inativos)
            $email = $user->email;
            $lookups = $lookupRepository->buscarAtivosPorEmail($email);
            $lookupsTodos = $lookupRepository->buscarTodosPorEmail($email); // Buscar todos incluindo inativos
            
            // ✅ LOG DETALHADO: Listar todos os lookups encontrados
            $lookupsArray = [];
            foreach ($lookups as $lookup) {
                $lookupsArray[] = [
                    'lookup_id' => $lookup->id ?? null,
                    'tenant_id' => $lookup->tenantId ?? null,
                    'user_id' => $lookup->userId ?? null,
                    'email' => $lookup->email ?? null,
                    'status' => $lookup->status ?? null,
                ];
            }
            
            $lookupsTodosArray = [];
            foreach ($lookupsTodos as $lookup) {
                $lookupsTodosArray[] = [
                    'lookup_id' => $lookup->id ?? null,
                    'tenant_id' => $lookup->tenantId ?? null,
                    'user_id' => $lookup->userId ?? null,
                    'email' => $lookup->email ?? null,
                    'status' => $lookup->status ?? null,
                ];
            }
            
            Log::info('ResolveTenantContext: Validando relação usuário-tenant', [
                'user_id' => $user->id,
                'user_email' => $email,
                'tenant_id_solicitado' => $tenantId,
                'total_lookups_ativos' => count($lookups),
                'total_lookups_todos' => count($lookupsTodos),
                'lookups_ativos_detalhes' => $lookupsArray,
                'lookups_todos_detalhes' => $lookupsTodosArray,
            ]);
            
            // Verificar se há registro ativo para este tenant_id e user_id
            foreach ($lookups as $lookup) {
                if ($lookup->tenantId === $tenantId && $lookup->userId === $user->id) {
                    // Relação válida encontrada
                    Log::info('ResolveTenantContext: ✅ Relação usuário-tenant VALIDADA na lookup', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'lookup_id' => $lookup->id ?? null,
                    ]);
                    return ['valido' => true];
                }
            }
            
            // ✅ LOG: Explicar por que não encontrou
            Log::warning('ResolveTenantContext: ❌ Relação NÃO encontrada na users_lookup', [
                'user_id' => $user->id,
                'tenant_id_solicitado' => $tenantId,
                'lookups_encontrados' => $lookupsArray,
                'razao' => 'Nenhum lookup com tenant_id=' . $tenantId . ' E user_id=' . $user->id . ' foi encontrado',
            ]);
            
            // Se não encontrou na lookup, validar diretamente no banco do tenant
            // (pode ser caso de usuário criado antes da lookup ser populada)
            $tenant = \App\Models\Tenant::find($tenantId);
            $validacaoDiretaPassou = false;
            
            if ($tenant) {
                Log::info('ResolveTenantContext: Tentando validação direta no banco do tenant', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'tenant_database' => $tenant->id ?? null,
                ]);
                
                tenancy()->initialize($tenant);
                try {
                    $userNoTenant = \App\Modules\Auth\Models\User::find($user->id);
                    $isValid = $userNoTenant !== null && !$userNoTenant->trashed();
                    
                    Log::info('ResolveTenantContext: Resultado da validação direta no tenant', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'usuario_encontrado' => $userNoTenant !== null,
                        'usuario_deletado' => $userNoTenant ? $userNoTenant->trashed() : null,
                        'valido' => $isValid,
                    ]);
                    
                    if ($isValid) {
                        Log::info('ResolveTenantContext: ✅ Relação validada diretamente no tenant', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenantId,
                        ]);
                        $validacaoDiretaPassou = true;
                    } else {
                        Log::warning('ResolveTenantContext: ❌ Validação direta no tenant FALHOU', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenantId,
                            'usuario_encontrado' => $userNoTenant !== null,
                            'usuario_deletado' => $userNoTenant ? $userNoTenant->trashed() : null,
                        ]);
                    }
                    
                    return ['valido' => $isValid];
                } finally {
                    tenancy()->end();
                }
            } else {
                Log::error('ResolveTenantContext: Tenant não encontrado para validação direta', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                ]);
            }
            
            // Se a validação direta passou, permitir acesso
            if ($validacaoDiretaPassou) {
                return ['valido' => true];
            }
            
            // ✅ Se não encontrou nada, tentar buscar usuário em TODOS os tenants possíveis
            Log::warning('ResolveTenantContext: Buscando usuário em TODOS os tenants para diagnóstico', [
                'user_id' => $user->id,
                'user_email' => $email,
                'tenant_id_solicitado' => $tenantId,
            ]);
            
            // Buscar em todos os tenants
            $todosTenants = \App\Models\Tenant::all();
            $tenantsComUsuario = [];
            
            foreach ($todosTenants as $t) {
                try {
                    tenancy()->initialize($t);
                    $userNoTenant = \App\Modules\Auth\Models\User::find($user->id);
                    if ($userNoTenant && !$userNoTenant->trashed()) {
                        $tenantsComUsuario[] = [
                            'tenant_id' => $t->id,
                            'tenant_razao_social' => $t->razao_social ?? null,
                            'usuario_encontrado' => true,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::debug('ResolveTenantContext: Erro ao verificar tenant', [
                        'tenant_id' => $t->id,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    tenancy()->end();
                }
            }
            
            if (empty($lookupsTodos) && empty($tenantsComUsuario)) {
                Log::error('ResolveTenantContext: ❌ VALIDAÇÃO FALHOU - Usuário não encontrado em nenhum tenant', [
                    'user_id' => $user->id,
                    'user_email' => $email,
                    'tenant_id_solicitado' => $tenantId,
                    'total_tenants_verificados' => count($todosTenants),
                    'problema' => 'Usuário não existe em nenhum tenant. Possível inconsistência de dados.',
                ]);
            } else if (!empty($tenantsComUsuario)) {
                // ✅ Usuário existe em outro(s) tenant(s), mas não no solicitado
                $tenantCorreto = $tenantsComUsuario[0]['tenant_id'] ?? null;
                
                Log::error('ResolveTenantContext: ❌ VALIDAÇÃO FALHOU - Usuário encontrado em outro tenant', [
                    'user_id' => $user->id,
                    'user_email' => $email,
                    'tenant_id_solicitado' => $tenantId,
                    'tenant_id_correto' => $tenantCorreto,
                    'tenants_onde_usuario_existe' => $tenantsComUsuario,
                    'total_tenants_verificados' => count($todosTenants),
                    'problema' => 'Usuário está no tenant ' . $tenantCorreto . ' mas o frontend está solicitando tenant ' . $tenantId,
                    'solucao' => 'Frontend deve usar tenant_id=' . $tenantCorreto . ' no header X-Tenant-ID',
                ]);
                
                // ✅ Retornar informação sobre o tenant correto
                return ['valido' => false, 'tenant_correto' => $tenantCorreto];
            } else {
                // ✅ LOG FINAL: Resumo do que foi tentado
                Log::error('ResolveTenantContext: ❌ VALIDAÇÃO FALHOU - Acesso negado', [
                    'user_id' => $user->id,
                    'user_email' => $email,
                    'tenant_id_solicitado' => $tenantId,
                    'tenants_validos_do_usuario' => array_map(fn($l) => $l->tenantId, $lookupsTodos),
                    'users_ids_validos' => array_map(fn($l) => $l->userId, $lookupsTodos),
                    'status_dos_lookups' => array_map(fn($l) => ['tenant_id' => $l->tenantId, 'status' => $l->status], $lookupsTodos),
                    'acao' => 'Nenhuma validação bem-sucedida. Acesso negado por segurança.',
                ]);
            }
            
            return ['valido' => false];
            
        } catch (\Exception $e) {
            Log::error('ResolveTenantContext: ❌ EXCEÇÃO ao validar relação usuário-tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'user_email' => $user->email ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Trace desabilitado',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            // Em caso de erro, negar acesso por segurança
            return ['valido' => false];
        }
    }
}
