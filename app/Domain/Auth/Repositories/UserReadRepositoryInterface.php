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
}

