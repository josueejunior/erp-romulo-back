<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Services;

/**
 * Interface para serviço de Pool de Bancos de Dados
 * 
 * Gerencia um pool de bancos pré-criados para reduzir latência no cadastro.
 * Em vez de criar banco e executar migrations em tempo real (15s),
 * apenas "anexa" um banco do pool ao novo tenant (200ms).
 */
interface TenantDatabasePoolServiceInterface
{
    /**
     * Obter um banco disponível do pool
     * 
     * @return string|null Nome do banco disponível ou null se pool vazio
     */
    public function obterBancoDoPool(): ?string;

    /**
     * Retornar banco ao pool (quando tenant é deletado)
     * 
     * @param string $databaseName Nome do banco a retornar
     */
    public function retornarBancoAoPool(string $databaseName): void;

    /**
     * Verificar se há bancos disponíveis no pool
     * 
     * @return bool
     */
    public function temBancosDisponiveis(): bool;

    /**
     * Contar bancos disponíveis no pool
     * 
     * @return int
     */
    public function contarBancosDisponiveis(): int;

    /**
     * Provisionar bancos no pool (criar bancos vazios com migrations)
     * 
     * @param int $quantidade Quantidade de bancos a criar
     * @return int Quantidade de bancos criados com sucesso
     */
    public function provisionarBancos(int $quantidade): int;
}

