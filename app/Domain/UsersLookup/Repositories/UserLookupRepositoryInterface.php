<?php

declare(strict_types=1);

namespace App\Domain\UsersLookup\Repositories;

use App\Domain\UsersLookup\Entities\UserLookup;

interface UserLookupRepositoryInterface
{
    /**
     * Busca um registro por email (apenas o primeiro encontrado)
     */
    public function buscarPorEmail(string $email): ?UserLookup;
    
    /**
     * Busca um registro por CNPJ (apenas o primeiro encontrado)
     */
    public function buscarPorCnpj(string $cnpj): ?UserLookup;
    
    /**
     * Busca TODOS os registros ativos por email
     * Retorna array porque pode haver múltiplos tenants com mesmo email
     */
    public function buscarAtivosPorEmail(string $email): array;
    
    /**
     * Busca TODOS os registros por email (ativos e inativos)
     * Retorna array porque pode haver múltiplos tenants com mesmo email
     */
    public function buscarTodosPorEmail(string $email): array;
    
    /**
     * Busca TODOS os registros ativos por CNPJ
     */
    public function buscarAtivosPorCnpj(string $cnpj): array;
    
    /**
     * Cria um novo registro
     */
    public function criar(UserLookup $lookup): UserLookup;
    
    /**
     * Atualiza um registro existente
     */
    public function atualizar(UserLookup $lookup): UserLookup;
    
    /**
     * Deleta um registro (soft delete)
     */
    public function deletar(int $id): void;
    
    /**
     * Marca um registro como inativo
     */
    public function marcarComoInativo(int $id): void;
    
    /**
     * Marca um registro como ativo
     */
    public function marcarComoAtivo(int $id): void;
    
    /**
     * Busca registros com filtros e paginação (para admin)
     * 
     * @param array $filtros ['search' => string, 'status' => string, 'per_page' => int, 'page' => int]
     * @return array ['data' => UserLookup[], 'total' => int, 'per_page' => int, 'current_page' => int]
     */
    public function buscarComFiltros(array $filtros = []): array;
}




