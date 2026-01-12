<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Enums;

/**
 * Enum para ações de auditoria
 * 
 * Segue DDD: Enum de domínio, sem dependências externas
 */
enum AuditAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case STATUS_CHANGED = 'status_changed';
    case PAYMENT_PROCESSED = 'payment_processed';
    case COMMISSION_GENERATED = 'commission_generated';
    case SUBSCRIPTION_ACTIVATED = 'subscription_activated';
    case SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    case COUPON_APPLIED = 'coupon_applied';
    case AFFILIATE_REFERRED = 'affiliate_referred';

    /**
     * Verifica se é uma ação crítica
     */
    public function isCritical(): bool
    {
        return in_array($this, [
            self::PAYMENT_PROCESSED,
            self::COMMISSION_GENERATED,
            self::SUBSCRIPTION_ACTIVATED,
            self::SUBSCRIPTION_CANCELLED,
        ]);
    }

    /**
     * Retorna descrição legível
     */
    public function description(): string
    {
        return match($this) {
            self::CREATED => 'Criado',
            self::UPDATED => 'Atualizado',
            self::DELETED => 'Excluído',
            self::STATUS_CHANGED => 'Status alterado',
            self::PAYMENT_PROCESSED => 'Pagamento processado',
            self::COMMISSION_GENERATED => 'Comissão gerada',
            self::SUBSCRIPTION_ACTIVATED => 'Assinatura ativada',
            self::SUBSCRIPTION_CANCELLED => 'Assinatura cancelada',
            self::COUPON_APPLIED => 'Cupom aplicado',
            self::AFFILIATE_REFERRED => 'Referência de afiliado',
        };
    }
}


