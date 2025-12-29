<?php

namespace App\Modules\Orgao\Policies;

use App\Modules\Orgao\Models\Orgao;
use App\Helpers\PermissionHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrgaoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any orgaos.
     */
    public function viewAny($user): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can view the orgao.
     */
    public function view($user, Orgao $orgao): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can create orgaos.
     */
    public function create($user): bool
    {
        return PermissionHelper::canManageMasterData();
    }

    /**
     * Determine whether the user can update the orgao.
     */
    public function update($user, Orgao $orgao): bool
    {
        return PermissionHelper::canManageMasterData();
    }

    /**
     * Determine whether the user can delete the orgao.
     */
    public function delete($user, Orgao $orgao): bool
    {
        if (!PermissionHelper::canManageMasterData()) {
            return false;
        }

        // Não permitir excluir se tiver processos vinculados
        if ($orgao->processos()->count() > 0) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the orgao.
     */
    public function restore($user, Orgao $orgao): bool
    {
        return PermissionHelper::canManageMasterData();
    }

    /**
     * Determine whether the user can permanently delete the orgao.
     */
    public function forceDelete($user, Orgao $orgao): bool
    {
        return $this->delete($user, $orgao);
    }
}

