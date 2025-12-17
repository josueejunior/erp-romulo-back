<?php

namespace App\Policies;

use App\Models\Orcamento;
use App\Models\Processo;
use App\Helpers\PermissionHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrcamentoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any orcamentos.
     */
    public function viewAny($user): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can view the orcamento.
     */
    public function view($user, Orcamento $orcamento): bool
    {
        return PermissionHelper::canView();
    }

    /**
     * Determine whether the user can create orcamentos.
     */
    public function create($user, $processo): bool
    {
        if (!PermissionHelper::canCreateProcess()) {
            return false;
        }

        // Se receber array [Processo], extrair o processo
        if (is_array($processo) && isset($processo[0])) {
            $processo = $processo[0];
        }

        // Só pode criar orçamento se processo estiver em participação
        return $processo && $processo->status === 'participacao';
    }

    /**
     * Determine whether the user can update the orcamento.
     */
    public function update($user, Orcamento $orcamento): bool
    {
        if (!PermissionHelper::canEditProcess()) {
            return false;
        }

        // Não pode editar orçamento de processo em execução
        if ($orcamento->processo && $orcamento->processo->isEmExecucao()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the orcamento.
     */
    public function delete($user, Orcamento $orcamento): bool
    {
        if (!PermissionHelper::canEditProcess()) {
            return false;
        }

        // Não pode deletar orçamento de processo em execução
        if ($orcamento->processo && $orcamento->processo->isEmExecucao()) {
            return false;
        }

        return true;
    }
}
