<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Tenant;
use Database\Seeders\Traits\HasTenantContext;
use Database\Seeders\Traits\HasUserCreation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class TestUserSeeder extends Seeder
{
    use HasTenantContext, HasUserCreation;

    protected function getOrCreateEmpresa(): Empresa
    {
        $empresa = Empresa::query()->first();
        if ($empresa) {
            return $empresa;
        }

        $cnpj = '99.999.999/0001-99';

        return Empresa::create([
            'razao_social' => 'Empresa Teste Addsimp',
            'cnpj' => $cnpj,
            'email' => 'empresa.teste@addsimp.com.br',
            'telefone' => '(11) 99999-9999',
            'status' => 'ativa',
        ]);
    }

    public function run(): void
    {
        $tenant = Tenant::query()->first();

        if (!$tenant) {
            $this->command->error('Nenhum tenant encontrado para criar usuário de teste.');
            return;
        }

        $this->withTenant($tenant, function () {
            $empresa = $this->getOrCreateEmpresa();

            $name = env('TEST_USER_NAME', 'Usuario Teste');
            $email = env('TEST_USER_EMAIL', 'teste@addsimp.com.br');
            $password = env('TEST_USER_PASSWORD', 'Teste@123');
            $role = env('TEST_USER_ROLE', 'Administrador');

            $perfilMap = [
                'Administrador' => 'admin',
                'Operacional' => 'operacional',
                'Financeiro' => 'financeiro',
                'Consulta' => 'consulta',
            ];

            $perfil = $perfilMap[$role] ?? 'consulta';

            $user = $this->createOrUpdateUser([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $this->associateUserToEmpresa($user, $empresa, $perfil);

            if (!Schema::hasTable('roles')) {
                $this->command->warn("   └─ Tabela 'roles' não existe no tenant. Pulando atribuição de role.");
            } elseif (Role::query()->where('name', $role)->exists()) {
                $user->syncRoles([$role]);
                $this->command->info("   └─ Role atribuída: {$role}");
            } else {
                $this->command->warn("   └─ Role '{$role}' não encontrada. Usuário criado sem role.");
            }

            $this->command->info('');
            $this->command->info('Usuário de teste pronto:');
            $this->command->info("  Email: {$email}");
            $this->command->info("  Senha: {$password}");
        });
    }
}

