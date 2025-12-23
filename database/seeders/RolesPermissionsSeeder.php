<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Permission\Models\Role;
use App\Modules\Permission\Models\Permission;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar permissões
        $permissions = [
            // Processos
            'processos.create',
            'processos.edit',
            'processos.delete',
            'processos.marcar-vencido',
            'processos.marcar-perdido',
            'processos.view',
            
            // Itens
            'processo-itens.create',
            'processo-itens.edit',
            'processo-itens.delete',
            
            // Orçamentos
            'orcamentos.create',
            'orcamentos.edit',
            'orcamentos.delete',
            
            // Formação de Preços
            'formacao-precos.create',
            'formacao-precos.edit',
            
            // Disputa
            'disputas.edit',
            
            // Julgamento
            'julgamentos.edit',
            
            // Custos
            'custos.manage',
            'custos.view',
            
            // Pagamentos
            'pagamentos.confirm',
            
            // Relatórios
            'relatorios.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Criar perfis
        $admin = Role::firstOrCreate(['name' => 'Administrador']);
        $operacional = Role::firstOrCreate(['name' => 'Operacional']);
        $financeiro = Role::firstOrCreate(['name' => 'Financeiro']);
        $consulta = Role::firstOrCreate(['name' => 'Consulta']);

        // Atribuir permissões aos perfis
        // Administrador - todas as permissões
        $admin->givePermissionTo(Permission::all());

        // Operacional - pode criar/editar processos, itens, orçamentos, disputas, julgamentos
        $operacional->givePermissionTo([
            'processos.create',
            'processos.edit',
            'processos.view',
            'processos.marcar-vencido',
            'processos.marcar-perdido',
            'processo-itens.create',
            'processo-itens.edit',
            'processo-itens.delete',
            'orcamentos.create',
            'orcamentos.edit',
            'orcamentos.delete',
            'formacao-precos.create',
            'formacao-precos.edit',
            'disputas.edit',
            'julgamentos.edit',
            'relatorios.view',
        ]);

        // Financeiro - pode gerenciar custos, confirmar pagamentos, ver relatórios
        $financeiro->givePermissionTo([
            'processos.view',
            'custos.manage',
            'custos.view',
            'pagamentos.confirm',
            'relatorios.view',
        ]);

        // Consulta - apenas visualização
        $consulta->givePermissionTo([
            'processos.view',
            'relatorios.view',
        ]);
    }
}
