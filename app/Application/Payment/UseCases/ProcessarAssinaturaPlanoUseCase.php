<?php

namespace App\Application\Payment\UseCases;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Payment\Entities\PaymentResult;
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

            // Se aprovado, criar assinatura
            if ($paymentResult->isApproved()) {
                return $this->criarAssinatura($tenant, $plano, $paymentResult, $diasValidade, $periodo);
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
     */
    private function criarAssinatura(
        Tenant $tenant,
        Plano $plano,
        PaymentResult $paymentResult,
        int $diasValidade,
        string $periodo
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
            // Criar assinatura com status pendente
            $assinatura = Assinatura::create([
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresa?->id, // 🔥 NOVO: Assinatura pertence à empresa
                'plano_id' => $plano->id,
                'status' => 'suspensa', // Será ativada quando o webhook confirmar
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

