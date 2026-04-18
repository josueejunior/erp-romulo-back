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
     * Criar banco de dados do tenant
     */
    public function criarBancoDados(Tenant $tenant): void;

    /**
     * Executar migrations no banco do tenant
     */
    public function executarMigrations(Tenant $tenant): void;

    /**
     * Encontrar o próximo número de tenant disponível
     * Verifica quais bancos já existem e retorna o próximo número livre
     * 
     * @return int Próximo número disponível para criar tenant
     */
    public function encontrarProximoNumeroDisponivel(): int;
}




