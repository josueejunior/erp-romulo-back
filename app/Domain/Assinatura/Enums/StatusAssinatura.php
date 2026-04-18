<?php

namespace App\Domain\Assinatura\Enums;

/**
 * Enum para status de assinatura
 * 
 * Centraliza todos os status válidos, evitando strings soltas no código.
 * Garante type-safety e autocomplete no IDE.
 */
enum StatusAssinatura: string
{
    case ATIVA = 'ativa';
    case CANCELADA = 'cancelada';
    case EXPIRADA = 'expirada';
    case PENDENTE = 'pendente';
    case SUSPENSA = 'suspensa';
    case TRIAL = 'trial';
    case AGUARDANDO_PAGAMENTO = 'aguardando_pagamento';

    /**
     * Verifica se o status representa uma assinatura válida/utilizável
     */
    public function isValida(): bool
    {
        return in_array($this, [
            self::ATIVA,
            self::TRIAL,
            self::PENDENTE, // grace period
        ]);
    }

    /**
     * Verifica se o status representa uma assinatura encerrada
     */
    public function isEncerrada(): bool
    {
        return in_array($this, [
            self::CANCELADA,
            self::EXPIRADA,
        ]);
    }

    /**
     * Verifica se permite upgrade de plano
     */
    public function permiteUpgrade(): bool
    {
        return in_array($this, [
            self::ATIVA,
            self::TRIAL,
        ]);
    }

    /**
     * Retorna label para exibição
     */
    public function label(): string
    {
        return match ($this) {
            self::ATIVA => 'Ativa',
            self::CANCELADA => 'Cancelada',
            self::EXPIRADA => 'Expirada',
            self::PENDENTE => 'Pendente',
            self::SUSPENSA => 'Suspensa',
            self::TRIAL => 'Período de Teste',
            self::AGUARDANDO_PAGAMENTO => 'Aguardando Pagamento',
        };
    }

    /**
     * Retorna cor para UI (Tailwind classes)
     */
    public function color(): string
    {
        return match ($this) {
            self::ATIVA => 'green',
            self::CANCELADA => 'red',
            self::EXPIRADA => 'gray',
            self::PENDENTE => 'yellow',
            self::SUSPENSA => 'orange',
            self::TRIAL => 'blue',
            self::AGUARDANDO_PAGAMENTO => 'yellow',
        };
    }

    /**
     * Lista de status que não devem aparecer em buscas de assinatura atual
     */
    public static function statusExcluidos(): array
    {
        return [
            self::CANCELADA->value,
            self::EXPIRADA->value,
        ];
    }

    /**
     * Lista de status ativos
     */
    public static function statusAtivos(): array
    {
        return [
            self::ATIVA->value,
            self::TRIAL->value,
        ];
    }

    /**
     * Cria a partir de string (com fallback)
     */
    public static function fromString(string $status): self
    {
        return self::tryFrom($status) ?? self::PENDENTE;
    }
}

