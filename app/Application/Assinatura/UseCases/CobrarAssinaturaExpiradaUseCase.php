<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Payment\UseCases\RenovarAssinaturaUseCase;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Cobrar Assinatura Expirada Automaticamente
 * 
 * Tenta renovar automaticamente uma assinatura expirada usando o último método de pagamento
 * 
 * NOTA: Atualmente não salvamos tokens de cartão por segurança.
 * Este Use Case estrutura o código para quando implementarmos salvamento seguro de cartão.
 */
class CobrarAssinaturaExpiradaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private RenovarAssinaturaUseCase $renovarAssinaturaUseCase,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @param int $assinaturaId ID da assinatura expirada
     * @return array Resultado da tentativa de cobrança
     */
    public function executar(int $tenantId, int $assinaturaId): array
    {
        // Buscar assinatura
        $assinaturaDomain = $this->assinaturaRepository->buscarPorId($assinaturaId);

        if (!$assinaturaDomain || $assinaturaDomain->tenantId !== $tenantId) {
            throw new \App\Domain\Exceptions\NotFoundException('Assinatura não encontrada.');
        }

        // Buscar modelo para acessar relacionamentos
        $assinatura = $this->assinaturaRepository->buscarModeloPorId($assinaturaId);
        if (!$assinatura) {
            throw new \App\Domain\Exceptions\NotFoundException('Assinatura não encontrada.');
        }

        // Validar que está expirada
        $hoje = \Carbon\Carbon::now();
        $dataFim = \Carbon\Carbon::parse($assinatura->data_fim);
        
        if ($dataFim->isFuture()) {
            throw new BusinessRuleException('A assinatura ainda não expirou.');
        }

        // Validar método de pagamento
        if (!$assinatura->metodo_pagamento || $assinatura->metodo_pagamento === 'gratuito') {
            return [
                'sucesso' => false,
                'motivo' => 'Assinatura gratuita ou sem método de pagamento salvo.',
                'mensagem' => 'Não é possível cobrar automaticamente assinaturas gratuitas.',
            ];
        }

        // Buscar plano
        $plano = $assinatura->plano;
        if (!$plano) {
            throw new \App\Domain\Exceptions\NotFoundException('Plano da assinatura não encontrado.');
        }

        // Calcular valor (renovar por 1 mês)
        $valor = $plano->preco_mensal;

        // NOTA: Para implementar cobrança automática real, precisaríamos:
        // 1. Salvar token do cartão de forma segura (criptografado)
        // 2. Ou usar Mercado Pago Customer/Card Token
        // 3. Ou usar assinatura recorrente do Mercado Pago
        
        // Por enquanto, apenas logar e retornar que precisa de ação manual
        Log::info('Tentativa de cobrança automática - requer token de cartão salvo', [
            'tenant_id' => $tenantId,
            'assinatura_id' => $assinaturaId,
            'metodo_pagamento' => $assinatura->metodo_pagamento,
            'valor' => $valor,
        ]);

        return [
            'sucesso' => false,
            'motivo' => 'Token de cartão não disponível.',
            'mensagem' => 'Para cobrança automática, é necessário salvar o método de pagamento. Entre em contato com o suporte ou renove manualmente.',
            'acao_requerida' => 'renovacao_manual',
            'valor' => $valor,
        ];

        // CÓDIGO FUTURO (quando implementar salvamento de cartão):
        /*
        // Buscar token do cartão salvo (exemplo)
        $cardToken = $this->buscarTokenCartaoSalvo($tenantId);
        
        if (!$cardToken) {
            return [
                'sucesso' => false,
                'motivo' => 'Token de cartão não encontrado.',
            ];
        }

        // Criar PaymentRequest
        $paymentRequest = PaymentRequest::fromArray([
            'amount' => $valor,
            'description' => "Renovação automática - Plano {$plano->nome}",
            'payer_email' => $assinatura->tenant->email,
            'card_token' => $cardToken,
            'installments' => 1,
            'payment_method_id' => $assinatura->metodo_pagamento === 'credit_card' ? 'credit_card' : null,
            'external_reference' => "auto_renewal_tenant_{$tenantId}_assinatura_{$assinaturaId}",
        ]);

        try {
            // Tentar renovar
            $assinaturaRenovada = $this->renovarAssinaturaUseCase->executar($assinatura, $paymentRequest, 1);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Assinatura renovada automaticamente com sucesso.',
                'assinatura_id' => $assinaturaRenovada->id,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao cobrar assinatura automaticamente', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'sucesso' => false,
                'motivo' => 'Erro ao processar pagamento.',
                'mensagem' => $e->getMessage(),
            ];
        }
        */
    }
}


