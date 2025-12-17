<?php

namespace App\Policies;

use App\Models\Contrato;
use App\Helpers\PermissionHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContratoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any contratos.
     */
    public function viewAny($user): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can view the contrato.
     */
    public function view($user, Contrato $contrato): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can create contratos.
     */
    public function create($user, $processo): bool
    {
        if (!PermissionHelper::canEditProcess()) {
            return false;
        }

        // Se receber array [Contrato, Processo], extrair o processo
        if (is_array($processo) && isset($processo[1])) {
            $processo = $processo[1];
        } elseif (is_array($processo) && isset($processo[0])) {
            $processo = $processo[0];
        }

        // Só pode criar contrato se processo estiver em execução
        return $processo && $processo->isEmExecucao();
    }

    /**
     * Determine whether the user can update the contrato.
     */
    public function update($user, Contrato $contrato): bool
    {
        return PermissionHelper::canEditProcess();
    }

    /**
     * Determine whether the user can delete the contrato.
     */
    public function delete($user, Contrato $contrato): bool
    {
        return PermissionHelper::canEditProcess();
    }
}
