<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class PermissionHelper
{
    /**
     * Verifica se o usuário tem permissão para criar processos
     */
    public static function canCreateProcess(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Operacional']);
    }

    /**
     * Verifica se o usuário pode editar processo
     */
    public static function canEditProcess(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Operacional']);
    }

    /**
     * Verifica se o usuário pode marcar processo como vencido/perdido
     */
    public static function canMarkProcessStatus(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Operacional']);
    }

    /**
     * Verifica se o usuário pode gerenciar custos
     */
    public static function canManageCosts(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Financeiro']);
    }

    /**
     * Verifica se o usuário pode confirmar pagamentos
     */
    public static function canConfirmPayments(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Financeiro']);
    }

    /**
     * Verifica se o usuário pode gerenciar documentos de habilitação
     * (criar/editar/excluir)
     */
    public static function canManageDocuments(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Operacional']);
    }

    /**
     * Verifica se o usuário pode visualizar relatórios financeiros
     */
    public static function canViewFinancialReports(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Usa permission específica se existir, senão cai no perfil
        if ($user->can('relatorios.view')) {
            return true;
        }

        return $user->hasAnyRole(['Administrador', 'Financeiro', 'Operacional', 'Consulta']);
    }

    /**
     * Verifica se o usuário pode gerenciar cadastros base (órgãos, fornecedores, etc.)
     */
    public static function canManageMasterData(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Administrador', 'Operacional']);
    }

    /**
     * Verifica se o usuário tem permissão para visualizar (qualquer perfil)
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
}



