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
     * Verifica se o usuário tem permissão para visualizar (qualquer perfil)
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
}



