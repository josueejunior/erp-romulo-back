<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface;
use App\Domain\UsersLookup\Entities\UserLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Comando para popular tabela users_lookup com dados existentes
 * 
 * ⚡ PERFORMANCE: Este comando popula a tabela users_lookup
 * para permitir validação rápida O(1) de email e CNPJ.
 * 
 * Execute após criar a migration: php artisan users:popular-lookup
 */
class PopularUsersLookupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:popular-lookup 
                            {--force : Forçar recriação de registros existentes}
                            {--tenant-id= : Popular apenas um tenant específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Popular tabela users_lookup com dados existentes de todos os tenants';

    public function __construct(
        private UserLookupRepositoryInterface $lookupRepository,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando popularização da tabela users_lookup...');
        $this->newLine();
        
        $force = $this->option('force');
        $tenantIdFilter = $this->option('tenant-id');
        
        // Buscar tenants
        $tenants = $tenantIdFilter 
            ? Tenant::where('id', $tenantIdFilter)->get()
            : Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return 0;
        }
        
        $this->info("Encontrados {$tenants->count()} tenant(s) para processar.");
        $this->newLine();
        
        $totalRegistros = 0;
        $totalErros = 0;
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();
        
        foreach ($tenants as $tenant) {
            try {
                $registros = $this->processarTenant($tenant, $force);
                $totalRegistros += $registros;
            } catch (\Exception $e) {
                $totalErros++;
                Log::error('Erro ao processar tenant no users_lookup', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                $this->newLine();
                $this->error("Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Resumo
        $this->info("✅ Concluído!");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Tenants processados', $tenants->count()],
                ['Registros criados/atualizados', $totalRegistros],
                ['Erros', $totalErros],
            ]
        );
        
        if ($totalErros > 0) {
            $this->warn("⚠️  {$totalErros} erro(s) ocorreram. Verifique os logs para detalhes.");
        }
        
        return 0;
    }
    
    /**
     * Processar um tenant e criar registros em users_lookup
     */
    private function processarTenant(Tenant $tenant, bool $force): int
    {
        $registros = 0;
        
        try {
            // Verificar se o banco de dados do tenant existe
            try {
                tenancy()->initialize($tenant);
            } catch (\Exception $e) {
                // Se não conseguir inicializar (banco não existe), pular este tenant
                Log::debug('PopularUsersLookupCommand: Tenant sem banco de dados, pulando', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                return 0;
            }
            
            try {
                // Buscar usuários do tenant
                $users = \App\Modules\Auth\Models\User::withoutTrashed()
                    ->whereNotNull('email')
                    ->get();
                
                if ($users->isEmpty()) {
                    return 0;
                }
                
                // Para cada usuário, buscar empresas vinculadas
                foreach ($users as $user) {
                    // Buscar empresas do usuário
                    $empresas = $user->empresas()->withoutTrashed()->get();
                    
                    if ($empresas->isEmpty()) {
                        // Se não tem empresa, usar dados do tenant como fallback
                        $cnpjLimpo = preg_replace('/\D/', '', $tenant->cnpj ?? '');
                        
                        if (!empty($user->email) && !empty($cnpjLimpo)) {
                            $lookup = new UserLookup(
                                id: null,
                                email: $user->email,
                                cnpj: $cnpjLimpo,
                                tenantId: $tenant->id,
                                userId: $user->id,
                                empresaId: null,
                                status: 'ativo',
                            );
                            
                            try {
                                // 🔥 SIMPLIFICAÇÃO: O método criar() já usa updateOrCreate com (email, tenant_id)
                                // como chave, então não precisamos verificar manualmente. Ele vai criar ou atualizar
                                // automaticamente de forma idempotente.
                                $this->lookupRepository->criar($lookup);
                                $registros++;
                            } catch (\Exception $e) {
                                Log::debug('PopularUsersLookupCommand: Erro ao criar lookup', [
                                    'tenant_id' => $tenant->id,
                                    'user_id' => $user->id,
                                    'email' => $user->email,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    } else {
                        // Para cada empresa do usuário, criar registro
                        foreach ($empresas as $empresa) {
                            $cnpjLimpo = preg_replace('/\D/', '', $empresa->cnpj ?? $tenant->cnpj ?? '');
                            
                            if (empty($cnpjLimpo) || empty($user->email)) {
                                continue;
                            }
                            
                            $lookup = new UserLookup(
                                id: null,
                                email: $user->email,
                                cnpj: $cnpjLimpo,
                                tenantId: $tenant->id,
                                userId: $user->id,
                                empresaId: $empresa->id,
                                status: 'ativo',
                            );
                            
                            try {
                                // 🔥 SIMPLIFICAÇÃO: O método criar() já usa updateOrCreate com (email, tenant_id)
                                // como chave, então não precisamos verificar manualmente. Ele vai criar ou atualizar
                                // automaticamente de forma idempotente.
                                $this->lookupRepository->criar($lookup);
                                $registros++;
                            } catch (\Exception $e) {
                                Log::debug('PopularUsersLookupCommand: Erro ao criar lookup', [
                                    'tenant_id' => $tenant->id,
                                    'user_id' => $user->id,
                                    'empresa_id' => $empresa->id,
                                    'email' => $user->email,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        } catch (\Exception $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            throw $e;
        }
        
        return $registros;
    }
}

