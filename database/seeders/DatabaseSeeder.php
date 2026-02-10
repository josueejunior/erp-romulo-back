<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Auth\Models\User;
use App\Models\Empresa;
use App\Models\Orgao;
use App\Modules\Orgao\Models\Setor;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\Traits\HasUserCreation;
use Database\Seeders\Traits\HasTenantContext;

class DatabaseSeeder extends Seeder
{
    use HasUserCreation, HasTenantContext;

    public function run(): void
    {
        // IMPORTANTE: AdminUserSeeder deve ser executado ANTES de qualquer tenancy
        // pois AdminUser é uma tabela central, não do tenant
        $this->call(AdminUserSeeder::class);
        
        // 🔥 DETECÇÃO DE CONTEXTO: Verificar se estamos executando no banco central
        // Quando executado via migrate:central --seed, não devemos criar dados do tenant
        $centralConnectionName = config('tenancy.database.central_connection', 'pgsql');
        $currentConnection = config('database.default');
        $isCentralContext = ($currentConnection === $centralConnectionName) && !tenancy()->initialized;
        
        if ($isCentralContext) {
            // Executando no banco central - apenas seeds do central
            $this->command->info('🌱 Executando seeds do banco central...');
            $this->call(PlanosSeeder::class);
            $this->command->info('✅ Seeds do banco central concluídos.');
            return; // Não executar seeds do tenant
        }
        
        // Executando no contexto do tenant - executar seeds completos
        $this->command->info('🌱 Executando seeds do tenant...');
        
        // Verificar se já existe um tenant com este CNPJ
        $tenant = \App\Models\Tenant::where('cnpj', '12.345.678/0001-90')->first();

        if (!$tenant) {
            // Criar tenant (empresa) no banco central
            // O ID será gerado automaticamente pelo banco (auto-increment)
            $tenant = \App\Models\Tenant::create([
                'razao_social' => 'Empresa Exemplo LTDA',
                'cnpj' => '12.345.678/0001-90',
                'email' => 'contato@exemplo.com',
                'status' => 'ativa',
            ]);

            // 🔥 ARQUITETURA SINGLE DATABASE:
            // Criar banco e executar migrations apenas se TENANCY_CREATE_DATABASES=true
            if (env('TENANCY_CREATE_DATABASES', false)) {
                try {
                    // Tentar usar o método direto primeiro
                    $tenant->database()->manager()->createDatabase($tenant);
                    $this->command->info('Banco de dados do tenant criado com sucesso');
                    
                    // Executar migrations
                    tenancy()->initialize($tenant);
                    \Artisan::call('migrate', [
                        '--path' => 'database/migrations/tenant',
                        '--force' => true
                    ]);
                    tenancy()->end();
                    $this->command->info('Migrations do tenant executadas com sucesso');
                } catch (\Exception $e) {
                    // Se falhar, tentar usar os jobs
                    try {
                        $this->command->warn('Tentando método alternativo para criar banco...');
                        CreateDatabase::dispatchSync($tenant);
                        MigrateDatabase::dispatchSync($tenant);
                        $this->command->info('Banco de dados do tenant criado com sucesso (método alternativo)');
                    } catch (\Exception $e2) {
                        $this->command->error('Erro ao criar banco do tenant: ' . $e2->getMessage());
                        $this->command->error('Erro original: ' . $e->getMessage());
                    }
                }
            } else {
                $this->command->info('Criação de banco desabilitada (Single Database Tenancy - isolamento por empresa_id)');
            }

            $this->command->info('Tenant criado: ' . $tenant->razao_social);
        } else {
            $this->command->info('Tenant já existe: ' . $tenant->razao_social);
            
            // Garantir que as migrations estejam executadas mesmo se o tenant já existir
            try {
                tenancy()->initialize($tenant);
                $this->command->info('Verificando migrations do tenant...');
                \Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true
                ]);
                tenancy()->end();
                $this->command->info('Migrations do tenant verificadas/executadas');
            } catch (\Exception $e) {
                tenancy()->end();
                $this->command->warn('Aviso ao verificar migrations: ' . $e->getMessage());
            }
        }

        // Inicializar o contexto do tenant
        tenancy()->initialize($tenant);

        // Limpar cache de permissões antes de criar roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar roles e permissões dentro do contexto do tenant
        $this->call(RolesPermissionsSeeder::class);
        
        // Limpar cache novamente após criar roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar planos de assinatura (tabela central, não do tenant)
        tenancy()->end();
        $this->call(PlanosSeeder::class);
        tenancy()->initialize($tenant);

        // Criar empresa dentro do tenant
        $empresa = Empresa::firstOrCreate(
            ['cnpj' => '12.345.678/0001-90'],
            [
                'razao_social' => 'Empresa Exemplo LTDA',
                'cnpj' => '12.345.678/0001-90',
                'email' => 'contato@exemplo.com',
                'status' => 'ativa',
            ]
        );
        $this->command->info('Empresa criada/verificada: ' . $empresa->razao_social);

        // Criar múltiplos usuários para teste
        $users = [
            [
                'name' => 'Administrador',
                'email' => 'admin@exemplo.com',
                'password' => 'password',
                'role' => 'Administrador',
            ],
            [
                'name' => 'Usuário Operacional',
                'email' => 'operacional@exemplo.com',
                'password' => 'password',
                'role' => 'Operacional',
            ],
            [
                'name' => 'Usuário Financeiro',
                'email' => 'financeiro@exemplo.com',
                'password' => 'password',
                'role' => 'Financeiro',
            ],
            [
                'name' => 'Usuário Consulta',
                'email' => 'consulta@exemplo.com',
                'password' => 'password',
                'role' => 'Consulta',
            ],
        ];

        foreach ($users as $userData) {
            $user = $this->createOrUpdateUser($userData, $userData['role'] ?? null);
            $this->associateUserToEmpresa($user, $empresa, strtolower($userData['role'] ?? 'consulta'));
        }

        // Verificar se já existe o órgão
        $orgao = Orgao::where('cnpj', '98.765.432/0001-10')->first();

        if (!$orgao) {
            // Criar órgão de exemplo dentro do tenant
            $orgao = Orgao::create([
                'empresa_id' => $empresa->id,
                'uasg' => '123456',
                'razao_social' => 'Órgão Público Exemplo',
                'cnpj' => '98.765.432/0001-10',
                'email' => 'contato@orgao.gov.br',
            ]);

            // Criar setor dentro do tenant
            Setor::create([
                'empresa_id' => $empresa->id,
                'orgao_id' => $orgao->id,
                'nome' => 'Setor de Compras',
                'email' => 'compras@orgao.gov.br',
            ]);

            $this->command->info('Órgão e setor criados no tenant');
        } else {
            $this->command->info('Órgão já existe no tenant');
        }

        // Finalizar o contexto do tenant
        tenancy()->end();

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('Dados iniciais criados com sucesso!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('Tenant ID: ' . $tenant->id);
        $this->command->info('Razão Social: ' . $tenant->razao_social);
        $this->command->info('');
        $this->command->info('Usuários criados:');
        $this->command->info('  - admin@exemplo.com (Administrador) - Senha: password');
        $this->command->info('  - operacional@exemplo.com (Operacional) - Senha: password');
        $this->command->info('  - financeiro@exemplo.com (Financeiro) - Senha: password');
        $this->command->info('  - consulta@exemplo.com (Consulta) - Senha: password');
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
