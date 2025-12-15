<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Orgao;
use App\Models\Setor;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Facades\Tenancy;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Verificar se já existe um tenant com este CNPJ
        $tenant = Tenant::where('cnpj', '12.345.678/0001-90')->first();

        if (!$tenant) {
            // Criar tenant (empresa) no banco central
            // O ID do tenant será gerado automaticamente se não fornecido
            $tenant = Tenant::create([
                'id' => Str::slug('empresa-exemplo'),
                'razao_social' => 'Empresa Exemplo LTDA',
                'cnpj' => '12.345.678/0001-90',
                'email' => 'contato@exemplo.com',
                'status' => 'ativa',
            ]);

            // Criar o banco de dados do tenant
            try {
                // Tentar usar o método direto primeiro
                $tenant->database()->manager()->createDatabase($tenant);
                $this->command->info('Banco de dados do tenant criado com sucesso');
                
                // Executar migrations
                tenancy()->initialize($tenant);
                \Artisan::call('tenants:migrate', ['--tenants' => $tenant->id]);
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

            $this->command->info('Tenant criado: ' . $tenant->razao_social);
        } else {
            $this->command->info('Tenant já existe: ' . $tenant->razao_social);
        }

        // Inicializar o contexto do tenant
        tenancy()->initialize($tenant);

        // Limpar cache de permissões antes de criar roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar roles e permissões dentro do contexto do tenant
        $this->call(RolesPermissionsSeeder::class);
        
        // Limpar cache novamente após criar roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
            $user = User::where('email', $userData['email'])->first();

            if (!$user) {
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                ]);

                // Atribuir role
                try {
                    $user->assignRole($userData['role']);
                    $this->command->info('Usuário criado: ' . $userData['email'] . ' (' . $userData['role'] . ')');
                } catch (\Exception $e) {
                    $this->command->error('Erro ao atribuir role ao usuário ' . $userData['email'] . ': ' . $e->getMessage());
                }
            } else {
                // Se o usuário já existe, garantir que tenha a role correta
                try {
                    // Remover todas as roles e atribuir a correta
                    $user->syncRoles([$userData['role']]);
                    $this->command->info('Usuário já existe: ' . $userData['email'] . ' - Role atualizada para: ' . $userData['role']);
                } catch (\Exception $e) {
                    $this->command->error('Erro ao atualizar role do usuário ' . $userData['email'] . ': ' . $e->getMessage());
                }
            }
        }

        // Verificar se já existe o órgão
        $orgao = Orgao::where('cnpj', '98.765.432/0001-10')->first();

        if (!$orgao) {
            // Criar órgão de exemplo dentro do tenant
            $orgao = Orgao::create([
                'uasg' => '123456',
                'razao_social' => 'Órgão Público Exemplo',
                'cnpj' => '98.765.432/0001-10',
                'email' => 'contato@orgao.gov.br',
            ]);

            // Criar setor dentro do tenant
            Setor::create([
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
