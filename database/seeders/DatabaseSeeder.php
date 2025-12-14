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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Criar roles e permissões
        $this->call(RolesPermissionsSeeder::class);

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
            $tenant->database()->manager()->createDatabase($tenant);

            $this->command->info('Tenant criado: ' . $tenant->razao_social);
        } else {
            $this->command->info('Tenant já existe: ' . $tenant->razao_social);
        }

        // Inicializar o contexto do tenant
        tenancy()->initialize($tenant);

        // Verificar se já existe o usuário admin
        $user = User::where('email', 'admin@exemplo.com')->first();

        if (!$user) {
            // Criar usuário admin dentro do tenant
            $user = User::create([
                'name' => 'Administrador',
                'email' => 'admin@exemplo.com',
                'password' => Hash::make('password'),
            ]);

            // Atribuir role de Administrador
            $user->assignRole('Administrador');

            $this->command->info('Usuário admin criado no tenant');
        } else {
            $this->command->info('Usuário admin já existe no tenant');
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
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('Dados iniciais criados com sucesso!');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('Tenant ID: ' . $tenant->id);
        $this->command->info('Email: admin@exemplo.com');
        $this->command->info('Senha: password');
        $this->command->info('═══════════════════════════════════════');
    }
}
