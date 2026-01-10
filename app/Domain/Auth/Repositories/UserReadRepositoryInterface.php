<?php

namespace App\Domain\Auth\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface para Read Model / Query Side
 * Separa leitura de escrita (CQRS pattern)
 * Controller nunca conhece Eloquent diretamente
 */
interface UserReadRepositoryInterface
{
    /**
     * Buscar usuário com relacionamentos para apresentação
     */
    public function buscarComRelacionamentos(int $userId): ?array;

    /**
     * Listar usuários com relacionamentos
     */
    public function listarComRelacionamentos(array $filtros = []): LengthAwarePaginator;

    /**
     * Buscar usuário por email
     * Usado para vincular usuário existente a uma nova empresa
     */
    public function buscarPorEmail(string $email): ?array;

    /**
     * Listar usuários sem paginação (para uso em listagens globais)
     * Retorna array de arrays com todos os usuários que atendem aos filtros
     * 
     * @param array $filtros
     * @return array Array de arrays representando usuários
     */
    public function listarSemPaginacao(array $filtros = []): array;
}

