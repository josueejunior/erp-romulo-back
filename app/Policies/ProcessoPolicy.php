<?php

namespace App\Policies;

use App\Models\Processo;
use App\Helpers\PermissionHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any processos.
     */
    public function viewAny($user): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can view the processo.
     */
    public function view($user, Processo $processo): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can create processos.
     */
    public function create($user): bool
    {
        return PermissionHelper::canCreateProcess();
    }

    /**
     * Determine whether the user can update the processo.
     */
    public function update($user, Processo $processo): bool
    {
        if (!PermissionHelper::canEditProcess()) {
            return false;
        }

        // Permitir atualização de data_recebimento_pagamento mesmo em execução
        if ($processo->isEmExecucao()) {
            // Verificar se apenas data_recebimento_pagamento está sendo atualizado
            $request = request();
            $onlyPaymentDate = $request->has('data_recebimento_pagamento') && 
                             count($request->except(['data_recebimento_pagamento'])) <= 1;
            return $onlyPaymentDate;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the processo.
     */
    public function delete($user, Processo $processo): bool
    {
        if (!PermissionHelper::canEditProcess()) {
            return false;
        }

        // Não permitir deletar processos em execução
        return !$processo->isEmExecucao();
    }

    /**
     * Determine whether the user can change status of the processo.
     */
    public function changeStatus($user, Processo $processo): bool
    {
        return PermissionHelper::canMarkProcessStatus();
    }

    /**
     * Determine whether the user can mark processo as vencido.
     */
    public function markVencido($user, Processo $processo): bool
    {
        return PermissionHelper::canMarkProcessStatus();
    }

    /**
     * Determine whether the user can mark processo as perdido.
     */
    public function markPerdido($user, Processo $processo): bool
    {
        return PermissionHelper::canMarkProcessStatus();
    }
}

