<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface do Repository de User
 * O domínio não sabe se é MySQL, MongoDB, API, etc.
 */
interface UserRepositoryInterface
{
    /**
     * Criar usuário administrador no tenant
     */
    public function criarAdministrador(
        int $tenantId,
        int $empresaId,
        string $nome,
        string $email,
        string $senha
    ): User;

    /**
     * Criar usuário comum
     */
    public function criar(User $user, int $empresaId, string $role): User;

    /**
     * Buscar usuário por ID
     */
    public function buscarPorId(int $id): ?User;

    /**
     * Buscar usuário por email
     */
    public function buscarPorEmail(string $email): ?User;

    /**
     * Verificar se email existe
     */
    public function emailExiste(string $email, ?int $excluirUserId = null): bool;

    /**
     * Listar usuários com filtros
     */
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;

    /**
     * Atualizar usuário
     */
    public function atualizar(User $user): User;

    /**
     * Deletar usuário (soft delete)
     */
    public function deletar(int $id): void;

    /**
     * Reativar usuário
     */
    public function reativar(int $id): void;

    /**
     * Atualizar role do usuário
     */
    public function atualizarRole(int $userId, string $role): void;

    /**
     * Sincronizar empresas do usuário
     */
    public function sincronizarEmpresas(int $userId, array $empresasIds): void;
}

