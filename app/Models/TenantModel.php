<?php

namespace App\Models;

/**
 * TenantModel
 * 
 * Classe base para todos os modelos que pertencem ao banco do tenant.
 * Automaticamente gerencia a conexão com o banco do tenant.
 * 
 * 🔥 IMPORTANTE: Use esta classe para modelos que estão no banco do tenant,
 * não no banco central. Modelos do banco central devem usar BaseModel diretamente.
 * 
 * Uso:
 * ```php
 * class Processo extends TenantModel
 * {
 *     // Não precisa definir getConnectionName() - já está implementado
 *     protected $fillable = [...];
 * }
 * ```
 */
abstract class TenantModel extends BaseModel
{
    /**
     * 🔥 IMPORTANTE: Usar conexão do tenant dinamicamente
     * Esta tabela está no banco do tenant, não no banco central
     * 
     * @return string|null Nome da conexão ('tenant' ou null para usar padrão)
     */
    public function getConnectionName(): ?string
    {
        // 🔥 PRIORIDADE 1: Verificar se a conexão padrão já é 'tenant' (mais rápido)
        $defaultConnection = config('database.default');
        if ($defaultConnection === 'tenant') {
            // Verificar se a conexão tenant está configurada corretamente
            $tenantDb = config('database.connections.tenant.database');
            if ($tenantDb && $tenantDb !== env('DB_DATABASE', 'laravel')) {
                return 'tenant';
            }
        }
        
        // 🔥 PRIORIDADE 2: Se o tenancy estiver inicializado, SEMPRE usar conexão do tenant
        // Isso garante que mesmo durante route binding (antes do middleware trocar conexão padrão),
        // o modelo use a conexão correta
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                $tenant = tenancy()->tenant;
                if ($tenant) {
                    // Garantir que a conexão tenant está configurada com o banco correto
                    $tenantDbName = $tenant->database()->getName();
                    config(['database.connections.tenant.database' => $tenantDbName]);
                    \Illuminate\Support\Facades\DB::purge('tenant');
                    return 'tenant';
                }
            }
        } catch (\Exception $e) {
            // Se houver erro ao verificar tenancy, continuar com verificação abaixo
        }
        
        // 🔥 PRIORIDADE 3: Verificar se há tenant_id no request (para route binding)
        // Isso ajuda quando o route binding é executado antes do middleware trocar a conexão
        try {
            $request = request();
            if ($request) {
                $tenantId = null;
                
                // Verificar header X-Tenant-ID
                if ($request->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) $request->header('X-Tenant-ID');
                }
                // Verificar JWT token (se disponível)
                elseif ($request->attributes->has('auth')) {
                    $auth = $request->attributes->get('auth');
                    if (isset($auth['tenant_id'])) {
                        $tenantId = (int) $auth['tenant_id'];
                    }
                }
                // 🔥 CRÍTICO: Verificar se estamos em uma rota de API (geralmente requer tenant)
                // Se a rota começa com /api/, tentar extrair tenant_id do JWT
                elseif (str_starts_with($request->path(), 'api/')) {
                    $token = $request->bearerToken();
                    if ($token) {
                        try {
                            $jwtService = app(\App\Services\JWTService::class);
                            $payload = $jwtService->getPayload($token);
                            if (isset($payload['tenant_id']) && $payload['tenant_id']) {
                                $tenantId = (int) $payload['tenant_id'];
                            }
                        } catch (\Exception $e) {
                            // Se falhar ao decodificar, continuar
                        }
                    }
                }
                
                // Se encontramos um tenant_id, inicializar tenancy e configurar conexão
                if ($tenantId) {
                    try {
                        $tenant = \App\Models\Tenant::find($tenantId);
                        if ($tenant) {
                            // Inicializar tenancy se ainda não estiver inicializado
                            if (!tenancy()->initialized || (tenancy()->initialized && tenancy()->tenant->id !== $tenantId)) {
                                tenancy()->initialize($tenant);
                            }
                            
                            // Configurar conexão tenant com o banco correto
                            $tenantDbName = $tenant->database()->getName();
                            config(['database.connections.tenant.database' => $tenantDbName]);
                            \Illuminate\Support\Facades\DB::purge('tenant');
                            
                            // Log para debug (apenas em desenvolvimento)
                            if (config('app.debug')) {
                                \Illuminate\Support\Facades\Log::debug('TenantModel: Conexão tenant configurada durante route binding', [
                                    'tenant_id' => $tenantId,
                                    'tenant_db' => $tenantDbName,
                                    'model' => static::class,
                                ]);
                            }
                            
                            return 'tenant';
                        }
                    } catch (\Exception $e) {
                        // Log do erro para debug
                        if (config('app.debug')) {
                            \Illuminate\Support\Facades\Log::error('TenantModel: Erro ao inicializar tenancy durante route binding', [
                                'tenant_id' => $tenantId,
                                'error' => $e->getMessage(),
                                'model' => static::class,
                            ]);
                        }
                        // Se falhar, continuar
                    }
                }
            }
        } catch (\Exception $e) {
            // Se houver erro, continuar
        }
        
        // Fallback: retornar null para usar conexão padrão
        // Mas isso só deve acontecer se realmente não estivermos em contexto de tenant
        return null;
    }
}


