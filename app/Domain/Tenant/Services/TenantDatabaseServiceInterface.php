<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Entities\Tenant;

/**
 * Interface para serviços de infraestrutura relacionados ao tenant
 * Separa a lógica de criação de banco de dados do domínio
 */
interface TenantDatabaseServiceInterface
{
    /**
     * Criar banco de dados do tenant.
     *
     * @param bool $forceCreate Se true, cria o banco mesmo quando TENANCY_CREATE_DATABASES=false (ex.: nova conta/cadastro público).
     */
    public function criarBancoDados(Tenant $tenant, bool $forceCreate = false): void;

    /**
     * Executar migrations no banco do tenant.
     *
     * @param bool $forceCreate Se true, executa mesmo quando TENANCY_CREATE_DATABASES=false (ex.: nova conta/cadastro público).
     */
    public function executarMigrations(Tenant $tenant, bool $forceCreate = false): void;

    /**
     * Encontrar o próximo número de tenant disponível
     * Verifica quais bancos já existem e retorna o próximo número livre
     * 
     * @return int Próximo número disponível para criar tenant
     */
    public function encontrarProximoNumeroDisponivel(): int;
}




