<?php

namespace App\Domain\Auth\Repositories;

/**
 * Interface do Repository de AdminUser
 * O domínio não sabe se é MySQL, MongoDB, API, etc.
 */
interface AdminUserRepositoryInterface
{
    /**
     * Buscar admin user por email
     * 
     * @param string $email
     * @return \App\Modules\Auth\Models\AdminUser|null
     */
    public function buscarPorEmail(string $email): ?\App\Modules\Auth\Models\AdminUser;

    /**
     * Buscar admin user por ID
     * 
     * @param int $id
     * @return \App\Modules\Auth\Models\AdminUser|null
     */
    public function buscarPorId(int $id): ?\App\Modules\Auth\Models\AdminUser;
}

