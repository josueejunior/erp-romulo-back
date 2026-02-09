<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;

/**
 * Cria o banco de dados (e executa migrations) para um ou todos os tenants
 * que ainda não possuem banco. Resolve o erro "database tenant_X does not exist".
 */
class TenantEnsureDatabases extends Command
{
    protected $signature = 'tenants:ensure-databases
                            {--tenants=* : IDs dos tenants (ex.: --tenants=2 --tenants=3). Vazio = todos}
                            {--force : Não pedir confirmação}';

    protected $description = 'Cria banco e executa migrations para tenants que ainda não têm banco (ex.: tenant_2)';

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        TenantDatabaseServiceInterface $databaseService,
    ): int {
        $ids = $this->option('tenants');
        $tenants = empty($ids)
            ? Tenant::orderBy('id')->get()
            : Tenant::whereIn('id', array_map('intval', $ids))->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return 0;
        }

        $this->info('Tenants a processar: ' . $tenants->pluck('id')->join(', '));
        if (!$this->option('force') && !$this->confirm('Continuar?', true)) {
            return 0;
        }

        $ok = 0;
        $fail = 0;

        foreach ($tenants as $tenantModel) {
            $tenantDomain = $tenantRepository->buscarPorId($tenantModel->id);
            if (!$tenantDomain) {
                $this->warn("Tenant {$tenantModel->id} não encontrado no repositório.");
                $fail++;
                continue;
            }

            $dbName = $tenantModel->database()->getName();
            $this->line("Tenant {$tenantModel->id} ({$tenantModel->razao_social}): banco {$dbName}");

            try {
                $databaseService->criarBancoDados($tenantDomain, forceCreate: true);
                $databaseService->executarMigrations($tenantDomain, forceCreate: true);
                $this->info("  OK: banco criado e migrations executadas.");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  Erro: " . $e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Concluído: {$ok} ok, {$fail} falha(s).");
        return $fail > 0 ? 1 : 0;
    }
}
