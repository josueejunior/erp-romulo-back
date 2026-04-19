<?php

declare(strict_types=1);

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Application\Payment\UseCases\RenovarAssinaturaUseCase;
use App\Domain\Payment\Events\PagamentoRecusado;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\BusinessRuleException;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Use Case: Cobrar Assinatura Expirada Automaticamente
 * 
 * 🔥 MELHORIA: Implementa cobrança automática usando Customer/Card ID do Mercado Pago
 * 
 * Raciocínio aplicado:
 * - External Vaulting: Usa customer_id e card_id (não são dados sensíveis)
 * - Idempotência: Chave baseada em assinatura_id + mês/ano (garante 1 cobrança/mês)
 * - Retry Inteligente: Máximo de 3 tentativas com intervalo de 24h
 * - Grace Period Ativo: Tenta cobrar durante o grace period antes de suspender
 */
class CobrarAssinaturaExpiradaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PaymentProviderInterface $paymentProvider,
        private RenovarAssinaturaUseCase $renovarAssinaturaUseCase,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Executa o caso de uso
     *
     * @param int $tenantId ID do tenant
     * @param int $assinaturaId ID da assinatura expirada
     * @param string|null $cvv CVV opcional — permite cobrança assistida com
     *        cartão salvo via /v1/payments (o MP exige security_code nesse
     *        fluxo). Para cobrança verdadeiramente automática sem CVV, usar
     *        Subscriptions API (/preapproval).
     * @return array Resultado da tentativa de cobrança
     */
    public function executar(int $tenantId, int $assinaturaId, ?string $cvv = null): array
    {
        // 1. Raciocínio de Guarda: Verificar se tem cartão salvo
        $assinatura = $this->assinaturaRepository->buscarModeloPorId($assinaturaId);
        
        if (!$assinatura || $assinatura->tenant_id !== $tenantId) {
            throw new \App\Domain\Exceptions\NotFoundException('Assinatura não encontrada.');
        }

        // Verificar se tem cartão salvo (External Vaulting)
        if (!$assinatura->hasCardToken()) {
            Log::info('CobrarAssinaturaExpiradaUseCase - Assinatura sem cartão salvo', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
            ]);

            return [
                'sucesso' => false,
                'motivo' => 'Cartão não salvo.',
                'mensagem' => 'Não é possível cobrar automaticamente. Por favor, renove manualmente e vincule um cartão para cobrança automática futura.',
                'acao_requerida' => 'renovacao_manual',
            ];
        }

        // Verificar se pode tentar cobrança (retry inteligente)
        if (!$assinatura->podeTentarCobranca()) {
            Log::info('CobrarAssinaturaExpiradaUseCase - Limite de tentativas atingido', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'tentativas' => $assinatura->tentativas_cobranca,
            ]);

            return [
                'sucesso' => false,
                'motivo' => 'Limite de tentativas atingido.',
                'mensagem' => 'Múltiplas tentativas de cobrança falharam. Por favor, atualize seu método de pagamento.',
                'acao_requerida' => 'atualizar_cartao',
            ];
        }

        // Validar que está expirada
        $hoje = Carbon::now();
        $dataFim = Carbon::parse($assinatura->data_fim);
        
        if ($dataFim->isFuture()) {
            throw new BusinessRuleException('A assinatura ainda não expirou.');
        }

        // Buscar plano
        $plano = $assinatura->plano;
        if (!$plano) {
            throw new \App\Domain\Exceptions\NotFoundException('Plano da assinatura não encontrado.');
        }

        // Calcular valor (renovar por 1 mês)
        $valor = $plano->preco_mensal;

        // 2. Chave de Idempotência baseada no período (garante que só cobra 1x/mês)
        $mesReferencia = $hoje->format('m');
        $anoReferencia = $hoje->format('Y');
        $idempotencyKey = "sub_{$assinaturaId}_{$mesReferencia}_{$anoReferencia}";

        // Verificar se já foi cobrado este mês (idempotência)
        $existingLog = DB::table('payment_logs')
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'approved')
            ->first();

        if ($existingLog) {
            Log::info('CobrarAssinaturaExpiradaUseCase - Cobrança já realizada este mês (idempotência)', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'idempotency_key' => $idempotencyKey,
            ]);

            return [
                'sucesso' => true,
                'motivo' => 'Cobrança já realizada este mês.',
                'mensagem' => 'A assinatura já foi renovada automaticamente este mês.',
            ];
        }

        // Buscar tenant para obter email
        $tenant = $assinatura->tenant;
        if (!$tenant) {
            throw new \App\Domain\Exceptions\NotFoundException('Tenant não encontrado.');
        }

        // Buscar dados da empresa para criar referência do pedido
        $empresaFinder = new \App\Domain\Tenant\Services\EmpresaFinder();
        $empresaData = $empresaFinder->findPrincipalByTenantId($tenant->id);
        $nomeEmpresa = $empresaData['razao_social'] ?? $tenant->razao_social ?? 'Empresa';
        $cnpjEmpresa = $empresaData['cnpj'] ?? $tenant->cnpj ?? '';
        
        // Criar referência do pedido: Nome da empresa_plano_cnpj
        $externalReference = $nomeEmpresa . '_' . $plano->nome . '_' . ($cnpjEmpresa ?: 'sem_cnpj');
        // Limitar tamanho (Mercado Pago aceita até 256 caracteres)
        $externalReference = substr($externalReference, 0, 256);

        // 3. Criar PaymentRequest usando card_id salvo (one-click buy)
        $metadata = [
            'tenant_id' => $tenantId,
            'assinatura_id' => $assinaturaId,
            'plano_id' => $plano->id,
            'tipo' => 'renovacao_automatica',
            'mes_referencia' => $mesReferencia,
            'ano_referencia' => $anoReferencia,
        ];
        // CVV viaja dentro do metadata (lido pelo gateway ao regenerar token).
        // Não é persistido — serve só como carona no request.
        if (!empty($cvv)) {
            $metadata['security_code'] = $cvv;
        }

        $paymentRequest = PaymentRequest::fromArray([
            'amount' => $valor,
            'description' => "Renovação automática - Plano {$plano->nome} - {$mesReferencia}/{$anoReferencia}",
            'payer_email' => $tenant->email,
            'payer_cpf' => null, // CPF já está no Customer
            'card_token' => null, // Não usar token, usar card_id
            'installments' => 1,
            'payment_method_id' => 'credit_card',
            'external_reference' => $externalReference,
            'metadata' => $metadata,
        ]);

        try {
            // 4. Processar pagamento usando card_id salvo
            $paymentResult = $this->paymentProvider->processPaymentWithSavedCard(
                request: $paymentRequest,
                customerId: $assinatura->mercado_pago_customer_id,
                cardId: $assinatura->mercado_pago_card_id,
                idempotencyKey: $idempotencyKey
            );

            // Atualizar contador de tentativas e última tentativa
            $assinatura->update([
                'ultima_tentativa_cobranca' => $hoje,
                'tentativas_cobranca' => $assinatura->tentativas_cobranca + 1,
            ]);

            // Se aprovado, renovar assinatura
            if ($paymentResult->isApproved()) {
                // Resetar contador de tentativas em caso de sucesso
                $assinatura->update([
                    'tentativas_cobranca' => 0,
                ]);

                // Renovar assinatura
                $assinaturaRenovada = $this->renovarAssinaturaUseCase->executar(
                    $assinatura,
                    $paymentRequest,
                    1 // 1 mês
                );

                Log::info('CobrarAssinaturaExpiradaUseCase - Cobrança automática realizada com sucesso', [
                    'tenant_id' => $tenantId,
                    'assinatura_id' => $assinaturaId,
                    'payment_id' => $paymentResult->externalId,
                    'valor' => $valor,
                ]);

                return [
                    'sucesso' => true,
                    'mensagem' => 'Assinatura renovada automaticamente com sucesso.',
                    'assinatura_id' => $assinaturaRenovada->id,
                    'payment_id' => $paymentResult->externalId,
                ];
            }

            // Se rejeitado, disparar evento PagamentoRecusado
            if ($paymentResult->isRejected()) {
                $event = new PagamentoRecusado(
                    assinaturaId: $assinaturaId,
                    tenantId: $tenantId,
                    empresaId: $assinatura->empresa_id,
                    motivo: 'Pagamento recusado pelo gateway',
                    errorMessage: $paymentResult->errorMessage ?? 'Pagamento recusado',
                    tentativasCobranca: $assinatura->tentativas_cobranca,
                    ocorridoEm: $hoje,
                );
                $this->eventDispatcher->dispatch($event);

                Log::warning('CobrarAssinaturaExpiradaUseCase - Pagamento recusado', [
                    'tenant_id' => $tenantId,
                    'assinatura_id' => $assinaturaId,
                    'error' => $paymentResult->errorMessage,
                    'tentativas' => $assinatura->tentativas_cobranca,
                ]);

                return [
                    'sucesso' => false,
                    'motivo' => 'Pagamento recusado.',
                    'mensagem' => $paymentResult->errorMessage ?? 'Pagamento recusado pelo banco. Por favor, atualize seu método de pagamento.',
                    'acao_requerida' => 'atualizar_cartao',
                    'tentativas' => $assinatura->tentativas_cobranca,
                ];
            }

            // Se pendente, aguardar webhook
            if ($paymentResult->isPending()) {
                Log::info('CobrarAssinaturaExpiradaUseCase - Pagamento pendente (aguardando webhook)', [
                    'tenant_id' => $tenantId,
                    'assinatura_id' => $assinaturaId,
                    'payment_id' => $paymentResult->externalId,
                ]);

                return [
                    'sucesso' => false,
                    'motivo' => 'Pagamento pendente.',
                    'mensagem' => 'Pagamento em processamento. Você será notificado quando for confirmado.',
                    'acao_requerida' => 'aguardar_confirmacao',
                ];
            }

            // Status desconhecido
            throw new DomainException('Status de pagamento desconhecido: ' . $paymentResult->status);

        } catch (DomainException $e) {
            // Atualizar contador de tentativas
            $assinatura->update([
                'ultima_tentativa_cobranca' => $hoje,
                'tentativas_cobranca' => $assinatura->tentativas_cobranca + 1,
            ]);

            Log::error('CobrarAssinaturaExpiradaUseCase - Erro ao processar cobrança', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'error' => $e->getMessage(),
                'tentativas' => $assinatura->tentativas_cobranca,
            ]);

            // Disparar evento PagamentoRecusado
            $event = new PagamentoRecusado(
                assinaturaId: $assinaturaId,
                tenantId: $tenantId,
                empresaId: $assinatura->empresa_id,
                motivo: 'Erro ao processar pagamento',
                errorMessage: $e->getMessage(),
                tentativasCobranca: $assinatura->tentativas_cobranca,
                ocorridoEm: $hoje,
            );
            $this->eventDispatcher->dispatch($event);

            return [
                'sucesso' => false,
                'motivo' => 'Erro ao processar pagamento.',
                'mensagem' => $e->getMessage(),
                'acao_requerida' => 'atualizar_cartao',
                'tentativas' => $assinatura->tentativas_cobranca,
            ];
        } catch (\Exception $e) {
            // Atualizar contador de tentativas
            $assinatura->update([
                'ultima_tentativa_cobranca' => $hoje,
                'tentativas_cobranca' => $assinatura->tentativas_cobranca + 1,
            ]);

            Log::error('CobrarAssinaturaExpiradaUseCase - Exceção ao processar cobrança', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tentativas' => $assinatura->tentativas_cobranca,
            ]);

            return [
                'sucesso' => false,
                'motivo' => 'Erro inesperado.',
                'mensagem' => 'Erro ao processar cobrança automática. Por favor, renove manualmente.',
                'acao_requerida' => 'renovacao_manual',
                'tentativas' => $assinatura->tentativas_cobranca,
            ];
        }
    }
}
