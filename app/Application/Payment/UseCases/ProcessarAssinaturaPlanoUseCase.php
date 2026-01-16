<?php

namespace App\Application\Payment\UseCases;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\Events\PagamentoProcessado;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Modules\Assinatura\Models\Plano;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use App\Models\PaymentLog;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case: Processar Assinatura de Plano
 * 
 * Application Layer - Orquestra o fluxo de pagamento e criação de assinatura
 */
class ProcessarAssinaturaPlanoUseCase
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Processa uma assinatura de plano
     * 
     * @param Tenant $tenant Tenant que está assinando
     * @param Plano $plano Plano a ser assinado
     * @param PaymentRequest $paymentRequest Dados do pagamento
     * @param string $periodo 'mensal' ou 'anual'
     * @return Assinatura Assinatura criada
     */
    public function executar(
        Tenant $tenant,
        Plano $plano,
        PaymentRequest $paymentRequest,
        string $periodo = 'mensal'
    ): Assinatura {
        // Validar plano
        if (!$plano->isAtivo()) {
            throw new DomainException('O plano selecionado não está ativo.');
        }

        // Calcular valor e data de expiração
        $valor = $periodo === 'anual' ? $plano->preco_anual : $plano->preco_mensal;
        $diasValidade = $periodo === 'anual' ? 365 : 30;

            // Validar valor
            if ($paymentRequest->amount->toReais() != $valor) {
                throw new DomainException('O valor do pagamento não corresponde ao valor do plano.');
            }

        // Gerar chave de idempotência única
        $idempotencyKey = $this->generateIdempotencyKey($tenant->id, $plano->id, $periodo);

        // 🔥 ROBUSTEZ: Verificar se já existe PaymentLog com esta chave (idempotência)
        // Usar transação com lock para evitar race condition em requisições simultâneas
        $existingLog = DB::transaction(function () use ($idempotencyKey) {
            return PaymentLog::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
        });
        
        if ($existingLog) {
            Log::warning('Tentativa de pagamento duplicado detectada (idempotência)', [
                'idempotency_key' => $idempotencyKey,
                'existing_log_id' => $existingLog->id,
                'existing_status' => $existingLog->status,
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
            ]);
            
            // Se já foi aprovado, retornar erro informando que já foi processado
            if ($existingLog->status === 'approved') {
                throw new DomainException('Este pagamento já foi processado anteriormente.');
            }
            
            // Se ainda está pendente, usar o log existente
            $paymentLog = $existingLog;
        } else {
            // Log da tentativa de pagamento
            $paymentLog = PaymentLog::create([
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
                'valor' => $valor,
                'periodo' => $periodo,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'metodo_pagamento' => $paymentRequest->paymentMethodId ?? 'credit_card',
                'dados_requisicao' => [
                    'payer_email' => $paymentRequest->payerEmail,
                    'description' => $paymentRequest->description,
                ],
            ]);
        }

        try {
            // Processar pagamento
            $paymentResult = $this->paymentProvider->processPayment($paymentRequest, $idempotencyKey);

            // Atualizar log com dados do pagamento
            $dadosResposta = [
                'status' => $paymentResult->status,
                'payment_method' => $paymentResult->paymentMethod,
                'error_message' => $paymentResult->errorMessage,
            ];

            // Se for PIX, incluir dados do QR Code
            if ($paymentResult->paymentMethod === 'pix') {
                $dadosResposta['pix_qr_code'] = $paymentResult->pixQrCode;
                $dadosResposta['pix_qr_code_base64'] = $paymentResult->pixQrCodeBase64;
                $dadosResposta['pix_ticket_url'] = $paymentResult->pixTicketUrl;
            }

            $paymentLog->update([
                'external_id' => $paymentResult->externalId,
                'status' => $paymentResult->status,
                'dados_resposta' => $dadosResposta,
            ]);

            // 🔥 DDD: Disparar Domain Event após pagamento processado
            $event = new PagamentoProcessado(
                paymentLogId: $paymentLog->id,
                tenantId: $tenant->id,
                assinaturaId: null, // Será preenchido após criar assinatura
                planoId: $plano->id,
                status: $paymentResult->status,
                valor: $valor,
                metodoPagamento: $paymentRequest->paymentMethodId ?? 'credit_card',
                externalId: $paymentResult->externalId,
                idempotencyKey: $idempotencyKey,
                userId: auth()->id(),
            );
            $this->eventDispatcher->dispatch($event);

            // Se aprovado, criar assinatura
            if ($paymentResult->isApproved()) {
                $assinatura = $this->criarAssinatura($tenant, $plano, $paymentResult, $diasValidade, $periodo, $paymentRequest);
                
                // Atualizar evento com assinaturaId criado (se necessário para listeners)
                // Por enquanto, o evento já foi disparado, mas listeners podem buscar depois
                
                return $assinatura;
            }

            // Se pendente (ex: PIX), criar assinatura pendente
            if ($paymentResult->isPending()) {
                return $this->criarAssinaturaPendente($tenant, $plano, $paymentResult, $diasValidade, $periodo);
            }

            // Se rejeitado, lançar exceção
            throw new DomainException(
                $paymentResult->errorMessage ?? 'Pagamento rejeitado pelo gateway.'
            );

        } catch (\Exception $e) {
            // Atualizar log com erro
            $paymentLog->update([
                'status' => 'failed',
                'dados_resposta' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            Log::error('Erro ao processar assinatura de plano', [
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cria assinatura aprovada
     * 
     * 🔥 MELHORIA: Cria Customer no Mercado Pago se pagamento for com cartão
     */
    private function criarAssinatura(
        Tenant $tenant,
        Plano $plano,
        PaymentResult $paymentResult,
        int $diasValidade,
        string $periodo,
        PaymentRequest $paymentRequest
    ): Assinatura {
        return DB::transaction(function () use ($tenant, $plano, $paymentResult, $diasValidade, $periodo) {
            $dataInicio = Carbon::now();
            $dataFim = $dataInicio->copy()->addDays($diasValidade);

            // 🔥 NOVO: Buscar empresa do tenant
            // IMPORTANTE: A tabela empresas dentro do tenant NÃO tem coluna tenant_id
            // Ela já está isolada por banco de dados. Buscar via mapeamento central primeiro.
            $empresa = null;
            
            // 1. Tentar buscar via TenantEmpresa (mapeamento central)
            $tenantEmpresa = \App\Models\TenantEmpresa::where('tenant_id', $tenant->id)->first();
            if ($tenantEmpresa) {
                // Buscar empresa no banco do tenant usando o empresa_id do mapeamento
                $empresa = \App\Models\Empresa::find($tenantEmpresa->empresa_id);
            }
            
            // 2. Se não encontrou, buscar a primeira empresa do tenant (sem filtro tenant_id)
            if (!$empresa) {
                $empresa = \App\Models\Empresa::where('excluido_em', null)->first();
            }
            
            if (!$empresa) {
                Log::warning('ProcessarAssinaturaPlanoUseCase - Nenhuma empresa encontrada no tenant', [
                    'tenant_id' => $tenant->id,
                ]);
            }

            // CRÍTICO: Cancelar assinaturas ativas antigas antes de criar a nova
            // Isso garante que apenas uma assinatura fique ativa por vez
            $assinaturasAntigas = Assinatura::where('tenant_id', $tenant->id)
                ->where('status', 'ativa')
                ->get();
            
            foreach ($assinaturasAntigas as $assinaturaAntiga) {
                $assinaturaAntiga->update([
                    'status' => 'cancelada',
                    'data_cancelamento' => now(),
                    'observacoes' => ($assinaturaAntiga->observacoes ?? '') . 
                        "\n\nCancelada automaticamente por upgrade de plano em " . now()->format('d/m/Y H:i:s'),
                ]);
                
                Log::info('Assinatura antiga cancelada por upgrade', [
                    'assinatura_antiga_id' => $assinaturaAntiga->id,
                    'plano_antigo_id' => $assinaturaAntiga->plano_id,
                    'tenant_id' => $tenant->id,
                ]);
            }

            // 🔥 MELHORIA: Criar Customer no Mercado Pago se pagamento for com cartão
            $customerId = null;
            $cardId = null;
            
            if ($paymentRequest->cardToken && $paymentResult->paymentMethod === 'credit_card') {
                try {
                    $customerData = $this->paymentProvider->createCustomerAndCard(
                        email: $paymentRequest->payerEmail,
                        cardToken: $paymentRequest->cardToken,
                        cpf: $paymentRequest->payerCpf
                    );
                    
                    $customerId = $customerData['customer_id'];
                    $cardId = $customerData['card_id'];
                    
                    Log::info('Customer e Card criados no Mercado Pago durante assinatura', [
                        'tenant_id' => $tenant->id,
                        'customer_id' => $customerId,
                        'card_id' => $cardId,
                    ]);
                } catch (\Exception $e) {
                    // Não bloquear criação da assinatura se falhar ao criar Customer
                    // A assinatura será criada, mas sem possibilidade de cobrança automática
                    Log::warning('Erro ao criar Customer/Card no Mercado Pago (não bloqueia assinatura)', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Criar nova assinatura
            $assinatura = Assinatura::create([
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresa?->id, // 🔥 NOVO: Assinatura pertence à empresa
                'plano_id' => $plano->id,
                'status' => 'ativa',
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'valor_pago' => $paymentResult->amount->toReais(),
                'metodo_pagamento' => $paymentResult->paymentMethod,
                'transacao_id' => $paymentResult->externalId,
                'dias_grace_period' => 7,
                // 🔥 MELHORIA: Salvar IDs do Customer/Card para cobrança futura
                'mercado_pago_customer_id' => $customerId,
                'mercado_pago_card_id' => $cardId,
            ]);

            // CRÍTICO: Atualizar tenant com plano e assinatura atuais
            $tenant->update([
                'plano_atual_id' => $plano->id,
                'assinatura_atual_id' => $assinatura->id,
            ]);
            
            // Forçar reload do tenant para garantir que os dados foram atualizados
            $tenant->refresh();

            Log::info('Assinatura criada e tenant atualizado com sucesso', [
                'tenant_id' => $tenant->id,
                'assinatura_id' => $assinatura->id,
                'plano_id' => $plano->id,
                'plano_atual_id_tenant' => $tenant->plano_atual_id,
                'assinatura_atual_id_tenant' => $tenant->assinatura_atual_id,
                'external_id' => $paymentResult->externalId,
                'assinaturas_canceladas' => $assinaturasAntigas->count(),
            ]);

            return $assinatura;
        });
    }

    /**
     * Cria assinatura pendente (ex: PIX aguardando pagamento)
     */
    private function criarAssinaturaPendente(
        Tenant $tenant,
        Plano $plano,
        PaymentResult $paymentResult,
        int $diasValidade,
        string $periodo
    ): Assinatura {
        return DB::transaction(function () use ($tenant, $plano, $paymentResult, $diasValidade, $periodo) {
            // 🔥 CRÍTICO: Verificar se já existe uma assinatura trial ativa
            // Se o cliente tem teste grátis e vai ao checkout mas não finaliza,
            // NÃO devemos criar uma nova assinatura pendente que substitua o trial
            $assinaturaTrialExistente = Assinatura::where('tenant_id', $tenant->id)
                ->where('status', 'trial')
                ->where('data_fim', '>=', Carbon::now())
                ->orderBy('data_fim', 'desc')
                ->first();
            
            if ($assinaturaTrialExistente) {
                Log::info('ProcessarAssinaturaPlanoUseCase - Assinatura trial existente encontrada, vinculando pagamento pendente', [
                    'tenant_id' => $tenant->id,
                    'trial_id' => $assinaturaTrialExistente->id,
                    'trial_status' => $assinaturaTrialExistente->status,
                    'trial_data_fim' => $assinaturaTrialExistente->data_fim,
                    'external_id' => $paymentResult->externalId,
                    'motivo' => 'Cliente tem teste grátis ativo - vinculando pagamento pendente à trial existente',
                ]);
                
                // 🔥 CRÍTICO: Atualizar a assinatura trial com o transacao_id do pagamento pendente
                // Isso permite que o webhook encontre a assinatura trial quando o pagamento for aprovado
                // e atualize ela para ativa, preservando o teste grátis
                $assinaturaTrialExistente->update([
                    'transacao_id' => $paymentResult->externalId,
                    'observacoes' => ($assinaturaTrialExistente->observacoes ?? '') . 
                        "\n\nPagamento pendente vinculado em " . now()->format('d/m/Y H:i:s') . 
                        " - Quando aprovado, a assinatura será atualizada para ativa.",
                ]);
                
                // Retornar a assinatura trial existente (agora com transacao_id vinculado)
                // Quando o pagamento for aprovado (via webhook), a trial será atualizada para ativa
                return $assinaturaTrialExistente;
            }
            
            $dataInicio = Carbon::now();
            $dataFim = $dataInicio->copy()->addDays($diasValidade);

            // 🔥 NOVO: Buscar empresa do tenant
            // IMPORTANTE: A tabela empresas dentro do tenant NÃO tem coluna tenant_id
            // Ela já está isolada por banco de dados. Buscar via mapeamento central primeiro.
            $empresa = null;
            
            // 1. Tentar buscar via TenantEmpresa (mapeamento central)
            $tenantEmpresa = \App\Models\TenantEmpresa::where('tenant_id', $tenant->id)->first();
            if ($tenantEmpresa) {
                // Buscar empresa no banco do tenant usando o empresa_id do mapeamento
                $empresa = \App\Models\Empresa::find($tenantEmpresa->empresa_id);
            }
            
            // 2. Se não encontrou, buscar a primeira empresa do tenant (sem filtro tenant_id)
            if (!$empresa) {
                $empresa = \App\Models\Empresa::where('excluido_em', null)->first();
            }
            
            if (!$empresa) {
                Log::warning('ProcessarAssinaturaPlanoUseCase - Nenhuma empresa encontrada no tenant', [
                    'tenant_id' => $tenant->id,
                ]);
            }

            // IMPORTANTE: NÃO atualizar tenant quando está pendente
            // O tenant só deve ser atualizado quando o pagamento for aprovado (via webhook)
            // Criar assinatura com status pendente (não suspensa, para evitar confusão)
            // Se o usuário voltar do checkout sem finalizar, a assinatura ficará pendente
            // e será cancelada automaticamente após um tempo ou quando o webhook confirmar rejeição
            $assinatura = Assinatura::create([
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresa?->id, // 🔥 NOVO: Assinatura pertence à empresa
                'plano_id' => $plano->id,
                'status' => 'pendente', // Mudado de 'suspensa' para 'pendente' - mais claro que está aguardando pagamento
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'valor_pago' => $paymentResult->amount->toReais(),
                'metodo_pagamento' => $paymentResult->paymentMethod,
                'transacao_id' => $paymentResult->externalId,
                'dias_grace_period' => 7,
                'observacoes' => 'Aguardando confirmação de pagamento - ' . ($paymentResult->errorMessage ?? 'Em análise'),
            ]);

            Log::info('Assinatura pendente criada (NÃO atualizou tenant)', [
                'tenant_id' => $tenant->id,
                'assinatura_id' => $assinatura->id,
                'external_id' => $paymentResult->externalId,
                'status_detail' => $paymentResult->errorMessage,
                'tenant_plano_atual_id' => $tenant->plano_atual_id, // Deve continuar o mesmo
            ]);

            return $assinatura;
        });
    }

    /**
     * Gera chave de idempotência única
     */
    private function generateIdempotencyKey(int $tenantId, int $planoId, string $periodo): string
    {
        return "tenant_{$tenantId}_plano_{$planoId}_{$periodo}_" . time();
    }
}

