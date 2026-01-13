<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Services\TenantDatabasePoolServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use Stancl\Tenancy\Tenancy;

/**
 * Serviço de Pool de Bancos de Dados
 * 
 * Gerencia um pool de bancos pré-criados para reduzir latência no cadastro.
 * 
 * Estratégia:
 * 1. Manter 10 bancos pré-criados: tenant_pool_1, tenant_pool_2, ..., tenant_pool_10
 * 2. No cadastro, buscar um banco disponível do pool
 * 3. Renomear o banco para tenant_{id} e anexar ao tenant
 * 4. Quando tenant é deletado, retornar banco ao pool (renomear de volta)
 */
final class TenantDatabasePoolService implements TenantDatabasePoolServiceInterface
{
    private const POOL_PREFIX = 'tenant_pool_';
    private const MIN_POOL_SIZE = 5; // Mínimo de bancos no pool
    private const MAX_POOL_SIZE = 20; // Máximo de bancos no pool

    /**
     * Obter um banco disponível do pool
     */
    public function obterBancoDoPool(): ?string
    {
        try {
            // Buscar primeiro banco disponível (não usado por nenhum tenant)
            $bancosPool = $this->listarBancosDoPool();
            
            foreach ($bancosPool as $banco) {
                // Verificar se o banco não está sendo usado por nenhum tenant
                if (!$this->bancoEstaEmUso($banco)) {
                    Log::info('TenantDatabasePoolService - Banco do pool encontrado', [
                        'database' => $banco,
                    ]);
                    return $banco;
                }
            }

            Log::warning('TenantDatabasePoolService - Pool vazio, nenhum banco disponível', [
                'pool_size' => count($bancosPool),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('TenantDatabasePoolService - Erro ao obter banco do pool', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Retornar banco ao pool (quando tenant é deletado)
     */
    public function retornarBancoAoPool(string $databaseName): void
    {
        try {
            // Se o banco já está no formato pool, não fazer nada
            if (str_starts_with($databaseName, self::POOL_PREFIX)) {
                return;
            }

            // Encontrar próximo nome disponível no pool
            $bancosPool = $this->listarBancosDoPool();
            $proximoNumero = $this->encontrarProximoNumeroDisponivel($bancosPool);

            if ($proximoNumero > self::MAX_POOL_SIZE) {
                Log::warning('TenantDatabasePoolService - Pool cheio, não é possível retornar banco', [
                    'database' => $databaseName,
                    'max_pool_size' => self::MAX_POOL_SIZE,
                ]);
                return;
            }

            $novoNome = self::POOL_PREFIX . $proximoNumero;

            // Renomear banco de volta para o pool
            DB::statement("ALTER DATABASE \"{$databaseName}\" RENAME TO \"{$novoNome}\"");

            Log::info('TenantDatabasePoolService - Banco retornado ao pool', [
                'database_antigo' => $databaseName,
                'database_novo' => $novoNome,
            ]);
        } catch (\Exception $e) {
            Log::error('TenantDatabasePoolService - Erro ao retornar banco ao pool', [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar se há bancos disponíveis no pool
     */
    public function temBancosDisponiveis(): bool
    {
        return $this->contarBancosDisponiveis() > 0;
    }

    /**
     * Contar bancos disponíveis no pool
     */
    public function contarBancosDisponiveis(): int
    {
        try {
            $bancosPool = $this->listarBancosDoPool();
            $disponiveis = 0;

            foreach ($bancosPool as $banco) {
                if (!$this->bancoEstaEmUso($banco)) {
                    $disponiveis++;
                }
            }

            return $disponiveis;
        } catch (\Exception $e) {
            Log::error('TenantDatabasePoolService - Erro ao contar bancos disponíveis', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Provisionar bancos no pool
     */
    public function provisionarBancos(int $quantidade): int
    {
        $criados = 0;
        $bancosExistentes = $this->listarBancosDoPool();
        $proximoNumero = $this->encontrarProximoNumeroDisponivel($bancosExistentes);

        for ($i = 0; $i < $quantidade; $i++) {
            $numero = $proximoNumero + $i;
            $databaseName = self::POOL_PREFIX . $numero;

            // Verificar se já existe
            if ($this->bancoExiste($databaseName)) {
                Log::info('TenantDatabasePoolService - Banco do pool já existe', [
                    'database' => $databaseName,
                ]);
                continue;
            }

            try {
                // Criar banco
                DB::statement("CREATE DATABASE \"{$databaseName}\"");

                // Criar tenant temporário para executar migrations
                $tenantTemporario = new Tenant([
                    'id' => 999999, // ID temporário
                    'razao_social' => 'TEMP_POOL',
                    'cnpj' => '00000000000000',
                    'status' => 'processing',
                ]);

                // Configurar conexão do tenant temporário para apontar ao banco do pool
                config(['database.connections.tenant.database' => $databaseName]);

                // Executar migrations no banco do pool
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                $criados++;

                Log::info('TenantDatabasePoolService - Banco do pool criado e migrado', [
                    'database' => $databaseName,
                ]);
            } catch (\Exception $e) {
                Log::error('TenantDatabasePoolService - Erro ao criar banco do pool', [
                    'database' => $databaseName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $criados;
    }

    /**
     * Listar todos os bancos do pool
     * 
     * @return array<string>
     */
    private function listarBancosDoPool(): array
    {
        try {
            $bancos = DB::select("
                SELECT datname 
                FROM pg_database 
                WHERE datname LIKE ? 
                ORDER BY datname
            ", [self::POOL_PREFIX . '%']);

            return array_map(fn($banco) => $banco->datname, $bancos);
        } catch (\Exception $e) {
            Log::error('TenantDatabasePoolService - Erro ao listar bancos do pool', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Verificar se banco está em uso por algum tenant
     */
    private function bancoEstaEmUso(string $databaseName): bool
    {
        try {
            // Verificar se algum tenant está usando este banco
            // O nome do banco do tenant é tenant_{id}
            // Se o banco do pool foi renomeado para tenant_{id}, está em uso
            $matches = [];
            if (preg_match('/^tenant_(\d+)$/', $databaseName, $matches)) {
                $tenantId = (int) $matches[1];
                $tenant = Tenant::find($tenantId);
                return $tenant !== null;
            }

            // Se ainda está no formato pool, não está em uso
            return false;
        } catch (\Exception $e) {
            Log::error('TenantDatabasePoolService - Erro ao verificar se banco está em uso', [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            return true; // Em caso de erro, assumir que está em uso para segurança
        }
    }

    /**
     * Verificar se banco existe
     */
    private function bancoExiste(string $databaseName): bool
    {
        try {
            $result = DB::select("
                SELECT 1 
                FROM pg_database 
                WHERE datname = ?
            ", [$databaseName]);

            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Encontrar próximo número disponível no pool
     */
    private function encontrarProximoNumeroDisponivel(array $bancosExistentes): int
    {
        if (empty($bancosExistentes)) {
            return 1;
        }

        $numeros = [];
        foreach ($bancosExistentes as $banco) {
            if (preg_match('/^' . preg_quote(self::POOL_PREFIX, '/') . '(\d+)$/', $banco, $matches)) {
                $numeros[] = (int) $matches[1];
            }
        }

        if (empty($numeros)) {
            return 1;
        }

        sort($numeros);
        $proximo = max($numeros) + 1;

        return $proximo;
    }
}

