<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class VerificarEmpresasTenants extends Command
{
    protected $signature = 'empresas:verificar-tenants {--user-id= : ID do usuário para verificar suas empresas}';
    protected $description = 'Verifica em qual tenant cada empresa está armazenada';

    public function handle()
    {
        $userId = $this->option('user-id');
        
        if ($userId) {
            $this->verificarEmpresasDoUsuario($userId);
        } else {
            $this->verificarTodasEmpresas();
        }
    }

    private function verificarEmpresasDoUsuario(int $userId)
    {
        $this->info("🔍 Verificando empresas do usuário ID: {$userId}");
        $this->newLine();

        // Buscar usuário no banco central
        $user = \App\Modules\Auth\Models\User::find($userId);
        
        if (!$user) {
            $this->error("Usuário não encontrado!");
            return;
        }

        $this->info("Usuário: {$user->name} ({$user->email})");
        $this->info("Empresa ativa: {$user->empresa_ativa_id}");
        $this->newLine();

        // Buscar empresas vinculadas ao usuário
        $empresasIds = $user->empresas()->pluck('empresas.id')->toArray();
        
        if (empty($empresasIds)) {
            $this->warn("Usuário não tem empresas vinculadas!");
            return;
        }

        $this->info("Empresas vinculadas: " . implode(', ', $empresasIds));
        $this->newLine();

        // Verificar cada tenant
        $tenants = Tenant::all();
        
        $this->table(
            ['Tenant ID', 'Tenant Nome', 'Database', 'Empresas Encontradas'],
            $tenants->map(function ($tenant) use ($empresasIds) {
                try {
                    tenancy()->initialize($tenant);
                    
                    $empresasNoTenant = \App\Models\Empresa::whereIn('id', $empresasIds)
                        ->get(['id', 'razao_social', 'cnpj']);
                    
                    $empresasInfo = $empresasNoTenant->map(function ($empresa) {
                        return "ID: {$empresa->id} - {$empresa->razao_social}";
                    })->join("\n");
                    
                    tenancy()->end();
                    
                    return [
                        $tenant->id,
                        $tenant->razao_social,
                        $tenant->database,
                        $empresasInfo ?: 'Nenhuma',
                    ];
                } catch (\Exception $e) {
                    tenancy()->end();
                    return [
                        $tenant->id,
                        $tenant->razao_social,
                        $tenant->database,
                        "ERRO: " . $e->getMessage(),
                    ];
                }
            })
        );

        $this->newLine();
        $this->info("✅ Verificação concluída!");
    }

    private function verificarTodasEmpresas()
    {
        $this->info("🔍 Verificando todas as empresas em todos os tenants");
        $this->newLine();

        $tenants = Tenant::all();
        
        $resultados = [];
        
        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                
                $empresas = \App\Models\Empresa::all(['id', 'razao_social', 'cnpj', 'status']);
                
                foreach ($empresas as $empresa) {
                    $resultados[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_nome' => $tenant->razao_social,
                        'tenant_database' => $tenant->database,
                        'empresa_id' => $empresa->id,
                        'empresa_razao_social' => $empresa->razao_social,
                        'empresa_cnpj' => $empresa->cnpj,
                        'empresa_status' => $empresa->status,
                    ];
                }
                
                tenancy()->end();
            } catch (\Exception $e) {
                $this->warn("Erro ao acessar tenant {$tenant->id}: " . $e->getMessage());
                tenancy()->end();
            }
        }

        if (empty($resultados)) {
            $this->warn("Nenhuma empresa encontrada!");
            return;
        }

        $this->table(
            ['Tenant ID', 'Tenant Nome', 'Database', 'Empresa ID', 'Empresa Nome', 'CNPJ', 'Status'],
            collect($resultados)->map(function ($row) {
                return [
                    $row['tenant_id'],
                    $row['tenant_nome'],
                    $row['tenant_database'],
                    $row['empresa_id'],
                    $row['empresa_razao_social'],
                    $row['empresa_cnpj'],
                    $row['empresa_status'],
                ];
            })
        );

        $this->newLine();
        $this->info("Total de empresas encontradas: " . count($resultados));
        $this->info("✅ Verificação concluída!");
    }
}

