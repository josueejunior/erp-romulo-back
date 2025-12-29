<?php

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Models\Tenant as TenantModel;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do serviço de banco de dados do tenant
 * Conhece detalhes de infraestrutura (Stancl Tenancy)
 */
class TenantDatabaseService implements TenantDatabaseServiceInterface
{
    public function criarBancoDados(Tenant $tenant): void
    {
        try {
            // Buscar modelo Eloquent do tenant
            $tenantModel = TenantModel::findOrFail($tenant->id);
            
            // Recarregar para garantir que está persistido
            $tenantModel->refresh();
            
            // Criar banco de dados
            CreateDatabase::dispatchSync($tenantModel);
        } catch (\Exception $e) {
            Log::error('Erro ao criar banco do tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Erro ao criar o banco de dados da empresa: ' . $e->getMessage());
        }
    }

    public function executarMigrations(Tenant $tenant): void
    {
        $tenantModel = TenantModel::findOrFail($tenant->id);
        MigrateDatabase::dispatchSync($tenantModel);
    }
}

