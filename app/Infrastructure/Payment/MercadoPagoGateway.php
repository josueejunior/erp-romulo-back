<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\NotFoundException;
use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Customer\CustomerCardClient;
use MercadoPago\MercadoPagoClient;

/**
 * Implementação do gateway Mercado Pago
 * 
 * Infrastructure Layer - Responsável pela comunicação com o Mercado Pago
 * Compatível com SDK versão 3.8.0+
 */
class MercadoPagoGateway implements PaymentProviderInterface
{
    private ?string $accessToken = null;
    private ?bool $isSandbox = null;
    private ?PaymentClient $paymentClient = null;
    private bool $initialized = false;

    /**
     * Inicializar o gateway (lazy loading)
     * Só inicializa quando realmente for usado
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Prioridade: SystemSetting (UI admin) → config/.env (fallback).
        // Permite ao admin rotacionar credenciais sem redeploy.
        $accessToken = \App\Models\SystemSetting::get(
            'mercadopago.access_token',
            config('services.mercadopago.access_token'),
        );
        $sandboxRaw = \App\Models\SystemSetting::get(
            'mercadopago.sandbox',
            config('services.mercadopago.sandbox', true),
        );
        $this->isSandbox = is_bool($sandboxRaw)
            ? $sandboxRaw
            : filter_var($sandboxRaw, FILTER_VALIDATE_BOOLEAN);

        if (empty($accessToken)) {
            throw new DomainException('Mercado Pago access token não configurado. Configure via painel Admin → Configurações → Pagamentos ou defina MP_ACCESS_TOKEN no .env.');
        }

        $this->accessToken = $accessToken;

        // Validar formato do token
        $tokenPrefix = $this->isSandbox ? 'TEST-' : 'APP_USR-';
        if (!str_starts_with($this->accessToken, $tokenPrefix)) {
            Log::warning('Formato de token do Mercado Pago pode estar incorreto', [
                'token_prefix' => substr($this->accessToken, 0, 10) . '...',
                'expected_prefix' => $tokenPrefix,
                'is_sandbox' => $this->isSandbox,
            ]);
        }

        // Inicializar SDK do Mercado Pago (versão 3.8.0+)
        MercadoPagoConfig::setAccessToken($this->accessToken);
        
        // Criar cliente de pagamento
        $this->paymentClient = new PaymentClient();
        
        $this->initialized = true;
    }

    /**
     * Processa um pagamento
     * 
     * 🔥 ROBUSTEZ: Protegido por Circuit Breaker para evitar travamentos quando API está instável
     */
    public function processPayment(PaymentRequest $request, string $idempotencyKey): PaymentResult
    {
        $this->initialize();
        
        // Criar circuit breaker para Mercado Pago
        $circuitBreaker = new CircuitBreaker(
            serviceName: 'mercadopago',
            failureThreshold: 5,
            timeout: 60, // 1 minuto
            halfOpenTimeout: 30 // 30 segundos
        );

        // Executar com circuit breaker
        return $circuitBreaker->call(
            operation: function () use ($request, $idempotencyKey) {
                return $this->executarProcessamentoPagamento($request, $idempotencyKey);
            },
            fallback: function () use ($request) {
                Log::error('Circuit breaker aberto - Mercado Pago indisponível', [
                    'payer_email' => $request->payerEmail,
                ]);
                throw new DomainException(
                    'O serviço de pagamento está temporariamente indisponível. ' .
                    'Por favor, tente novamente em alguns instantes. Se o problema persistir, entre em contato com o suporte.'
                );
            }
        );
    }

    /**
     * Executa o processamento real do pagamento (isolado para circuit breaker)
     */
    private function executarProcessamentoPagamento(PaymentRequest $request, string $idempotencyKey): PaymentResult
    {
        try {
            // Detectar método de pagamento
            $isPix = $request->paymentMethodId === 'pix';
            $isCartao = !$isPix && !empty($request->cardToken);

            // Validações específicas por método
            if ($isCartao && empty($request->cardToken)) {
                throw new DomainException('Token do cartão é obrigatório para pagamento com cartão.');
            }

            if ($isPix && !empty($request->cardToken)) {
                throw new DomainException('Token do cartão não deve ser enviado para pagamento PIX.');
            }

            // Preparar dados do pagamento para a nova API
            $paymentData = [
                'transaction_amount' => (float) round($request->amount->toReais(), 2), // Garantir 2 casas decimais
                'description' => substr($request->description, 0, 255), // Limitar tamanho
                'payer' => [
                    'email' => $request->payerEmail,
                ],
            ];

            // Configurações específicas por método de pagamento
            if ($isPix) {
                // PIX: não precisa de token, precisa de payment_method_id
                $paymentData['payment_method_id'] = 'pix';
                // PIX não tem parcelas
            } else {
                // Cartão: precisa de token, parcelas
                $paymentData['installments'] = (int) ($request->installments ?? 1);
                // NÃO enviar payment_method_id fixo - o token já contém essa informação
                // Se enviar errado, causa erro diff_param_bins
                $paymentData['token'] = (string) $request->cardToken;
            }

            // CPF ou CNPJ do pagador (obrigatório para cartão, recomendado para PIX)
            if ($request->payerCpf) {
                $documentoLimpo = preg_replace('/\D/', '', $request->payerCpf);
                if (strlen($documentoLimpo) === 11) {
                    // CPF (11 dígitos)
                    $paymentData['payer']['identification'] = [
                        'type' => 'CPF',
                        'number' => $documentoLimpo,
                    ];
                } elseif (strlen($documentoLimpo) === 14) {
                    // CNPJ (14 dígitos)
                    $paymentData['payer']['identification'] = [
                        'type' => 'CNPJ',
                        'number' => $documentoLimpo,
                    ];
                }
            } else {
                if ($isCartao) {
                    Log::warning('CPF/CNPJ do pagador não fornecido para pagamento com cartão', [
                        'payer_email' => $request->payerEmail,
                    ]);
                }
            }

            // External reference (opcional, mas recomendado)
            if ($request->externalReference) {
                $paymentData['external_reference'] = substr($request->externalReference, 0, 256);
            } else {
                $paymentData['external_reference'] = substr($idempotencyKey, 0, 256);
            }

            // Metadados (opcional)
            if ($request->metadata && is_array($request->metadata)) {
                $paymentData['metadata'] = $request->metadata;
            }

            // Statement descriptor (opcional, mas recomendado)
            $paymentData['statement_descriptor'] = 'SISTEMA ROMULO';
            
            // NÃO enviar issuer_id - deixar o Mercado Pago detectar do token
            // Enviar issuer_id errado causa erro diff_param_bins

            // Log dos dados antes de enviar (sem dados sensíveis)
            // IMPORTANTE: Log completo para debug do erro diff_param_bins
            Log::info('Payload Final MP (antes de enviar):', [
                'transaction_amount' => $paymentData['transaction_amount'],
                'description' => $paymentData['description'],
                'installments' => $paymentData['installments'] ?? 'N/A (PIX não tem parcelas)',
                'has_payment_method_id' => isset($paymentData['payment_method_id']),
                'payment_method_id' => $paymentData['payment_method_id'] ?? 'NÃO ENVIADO (correto - será detectado do token)',
                'has_token' => !empty($paymentData['token']),
                'token_length' => strlen($paymentData['token'] ?? ''),
                'token_preview' => !empty($paymentData['token']) ? substr($paymentData['token'], 0, 15) . '...' : 'vazio',
                'payer_email' => $paymentData['payer']['email'],
                'has_cpf' => isset($paymentData['payer']['identification']),
                'cpf' => $paymentData['payer']['identification']['number'] ?? 'não fornecido',
                'has_issuer_id' => isset($paymentData['issuer_id']),
                'issuer_id' => $paymentData['issuer_id'] ?? 'NÃO ENVIADO (correto - será detectado do token)',
                'external_reference' => $paymentData['external_reference'] ?? 'não fornecido',
                'statement_descriptor' => $paymentData['statement_descriptor'] ?? 'não fornecido',
                'payment_data_keys' => array_keys($paymentData),
                'is_sandbox' => $this->isSandbox,
            ]);

            // Criar pagamento usando a nova API
            // IMPORTANTE: O SDK do Mercado Pago espera um array associativo
            try {
                $payment = $this->paymentClient->create($paymentData);
            } catch (\Exception $e) {
                Log::error('Erro ao chamar PaymentClient->create', [
                    'exception' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'payment_data_keys' => array_keys($paymentData),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            // Log para depuração
            Log::debug('Resposta do Mercado Pago', [
                'payment_type' => gettype($payment),
                'payment_class' => is_object($payment) ? get_class($payment) : null,
                'payment_preview' => is_array($payment) ? array_keys($payment) : (is_object($payment) ? get_object_vars($payment) : null),
            ]);

            // Verificar se o resultado é um array e se contém erro
            if (is_array($payment) && isset($payment['error'])) {
                $errorMessage = $payment['error']['message'] ?? 'Erro desconhecido no pagamento';
                $errorCode = $payment['error']['code'] ?? null;
                $errorCause = $payment['error']['cause'] ?? null;
                
                Log::error('Erro ao processar pagamento no Mercado Pago', [
                    'error' => $payment['error'],
                    'error_code' => $errorCode,
                    'error_cause' => $errorCause,
                    'idempotency_key' => $idempotencyKey,
                    'payment_data' => $paymentData,
                ]);

                $detailedMessage = $errorMessage;
                if ($errorCode) {
                    $detailedMessage .= " (Código: {$errorCode})";
                }
                if ($errorCause && is_array($errorCause) && !empty($errorCause)) {
                    $causeDetails = array_map(function($cause) {
                        return $cause['description'] ?? $cause['code'] ?? 'Erro desconhecido';
                    }, $errorCause);
                    $detailedMessage .= " - " . implode(', ', $causeDetails);
                }

                // Tratamento específico para erros conhecidos
                if (str_contains(strtolower($detailedMessage), 'collector user without key enabled for qr render') || 
                    str_contains(strtolower($detailedMessage), 'qr render') ||
                    str_contains(strtolower($detailedMessage), 'pix not enabled')) {
                    $detailedMessage = 'PIX não está habilitado na sua conta do Mercado Pago. Por favor, use cartão de crédito ou entre em contato com o suporte para habilitar PIX.';
                } elseif (str_contains(strtolower($detailedMessage), 'unauthorized') || str_contains(strtolower($detailedMessage), 'policy')) {
                    $detailedMessage = 'Erro de autenticação no Mercado Pago. Verifique se o Access Token está correto e tem as permissões necessárias. ' . 
                                      'Certifique-se de estar usando o token correto (sandbox ou produção) e que ele tenha permissão para criar pagamentos.';
                } elseif (str_contains(strtolower($detailedMessage), 'diff_param_bins') || str_contains(strtolower($errorCode ?? ''), 'diff_param_bins')) {
                    $detailedMessage = 'Erro nos parâmetros do pagamento. Verifique se o token do cartão foi gerado corretamente e se todos os dados estão completos. ' .
                                      'Certifique-se de que o cartão é válido e que os dados do pagador estão corretos.';
                }

                throw new DomainException("Erro no pagamento: {$detailedMessage}");
            }

            // Verificar se o resultado é um objeto com propriedades de erro
            if (is_object($payment)) {
                // Verificar se tem propriedade error ou getError
                if (method_exists($payment, 'getError') || property_exists($payment, 'error')) {
                    $error = method_exists($payment, 'getError') ? $payment->getError() : $payment->error;
                    if ($error) {
                        $errorMessage = is_string($error) ? $error : ($error['message'] ?? 'Erro desconhecido no pagamento');
                        Log::error('Erro ao processar pagamento no Mercado Pago (objeto)', [
                            'error' => $error,
                            'idempotency_key' => $idempotencyKey,
                            'payment_data' => $paymentData,
                        ]);
                        throw new DomainException("Erro no pagamento: {$errorMessage}");
                    }
                }
            }

            // Retornar resultado
            return $this->mapPaymentToResult($payment);

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            // Capturar exceção específica do Mercado Pago
            $apiResponse = $e->getApiResponse();
            $content = $apiResponse ? $apiResponse->getContent() : null;
            
            $errorMessage = 'Erro na API do Mercado Pago';
            $statusCode = $apiResponse ? $apiResponse->getStatusCode() : null;
            
            if ($content) {
                $errorMessage = $content['message'] ?? $errorMessage;
                if (isset($content['error'])) {
                    $errorMessage = $content['error']['message'] ?? $errorMessage;
                    if (isset($content['error']['cause']) && is_array($content['error']['cause'])) {
                        $causes = array_map(function($cause) {
                            return $cause['description'] ?? $cause['code'] ?? '';
                        }, $content['error']['cause']);
                        $errorMessage .= ' - ' . implode(', ', array_filter($causes));
                    }
                }
            }

            // Tratamento específico para erro de PIX não habilitado
            if (str_contains(strtolower($errorMessage), 'collector user without key enabled for qr render') || 
                str_contains(strtolower($errorMessage), 'qr render') ||
                str_contains(strtolower($errorMessage), 'pix not enabled')) {
                $errorMessage = 'PIX não está habilitado na sua conta do Mercado Pago. Por favor, use cartão de crédito ou entre em contato com o suporte para habilitar PIX.';
            }
            
            // Tratamento específico para erro de autorização
            if ($statusCode === 401 || str_contains(strtolower($errorMessage), 'unauthorized') || str_contains(strtolower($errorMessage), 'policy')) {
                $errorMessage = 'Erro de autenticação no Mercado Pago. Verifique se o Access Token está correto e tem as permissões necessárias. ' . 
                               'Certifique-se de estar usando o token correto (sandbox ou produção) e que ele tenha permissão para criar pagamentos.';
            }
            
            Log::error('Exceção MPApiException ao processar pagamento no Mercado Pago', [
                'exception' => $e->getMessage(),
                'api_response' => $content,
                'status_code' => $statusCode,
                'idempotency_key' => $idempotencyKey,
                'is_sandbox' => $this->isSandbox,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DomainException("Erro ao processar pagamento: {$errorMessage}");
        } catch (\Exception $e) {
            Log::error('Exceção ao processar pagamento no Mercado Pago', [
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'idempotency_key' => $idempotencyKey,
            ]);

            throw new DomainException("Erro ao processar pagamento: {$e->getMessage()}");
        }
    }

    /**
     * Consulta o status de um pagamento
     * 
     * 🔥 ROBUSTEZ: Protegido por Circuit Breaker
     */
    public function getPaymentStatus(string $externalId): PaymentResult
    {
        $this->initialize();
        
        $circuitBreaker = new CircuitBreaker(
            serviceName: 'mercadopago',
            failureThreshold: 5,
            timeout: 60,
            halfOpenTimeout: 30
        );

        return $circuitBreaker->call(
            operation: function () use ($externalId) {
                try {
                    $payment = $this->paymentClient->get($externalId);

                    // $payment é um objeto MercadoPago\Resources\Payment, não um array
                    if (!$payment) {
                        throw new NotFoundException("Pagamento não encontrado: {$externalId}");
                    }

                    // O mapPaymentToResult já trata a conversão de objeto para array
                    // Não tentar acessar propriedades como array aqui
                    return $this->mapPaymentToResult($payment);

                } catch (NotFoundException $e) {
                    throw $e; // NotFoundException não conta como falha para circuit breaker
                } catch (\MercadoPago\Exceptions\MPApiException $e) {
                    // Qualquer 4xx do MP (404 ID inexistente, 400 ID inválido,
                    // etc.) é comportamento esperado — não conta como falha
                    // do circuit breaker, e o controller devolve 404 amigável
                    // para o cliente.
                    $apiResp = $e->getApiResponse();
                    $status = $apiResp ? $apiResp->getStatusCode() : null;
                    if ($status !== null && $status >= 400 && $status < 500) {
                        throw new NotFoundException("Pagamento não encontrado: {$externalId}");
                    }
                    Log::error('Erro ao consultar status do pagamento no Mercado Pago', [
                        'external_id' => $externalId,
                        'exception' => $e->getMessage(),
                        'status' => $status,
                    ]);
                    throw new DomainException("Erro ao consultar pagamento: {$e->getMessage()}");
                } catch (\Exception $e) {
                    Log::error('Erro ao consultar status do pagamento no Mercado Pago', [
                        'external_id' => $externalId,
                        'exception' => $e->getMessage(),
                    ]);

                    throw new DomainException("Erro ao consultar pagamento: {$e->getMessage()}");
                }
            },
            fallback: function () use ($externalId) {
                Log::error('Circuit breaker aberto - não foi possível consultar status do pagamento', [
                    'external_id' => $externalId,
                ]);
                throw new DomainException(
                    'O serviço de pagamento está temporariamente indisponível para consultas. ' .
                    'Tente novamente em alguns instantes.'
                );
            }
        );
    }

    /**
     * Processa um webhook do Mercado Pago
     */
    public function processWebhook(array $payload): PaymentResult
    {
        try {
            // O Mercado Pago envia o tipo de evento e o ID do pagamento
            $type = $payload['type'] ?? null;
            $paymentId = $payload['data']['id'] ?? null;

            if (!$paymentId) {
                throw new DomainException('ID do pagamento não encontrado no webhook.');
            }

            // Consultar status atualizado
            return $this->getPaymentStatus($paymentId);

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'payload' => $payload,
                'exception' => $e->getMessage(),
            ]);

            throw new DomainException("Erro ao processar webhook: {$e->getMessage()}");
        }
    }

    /**
     * Valida a assinatura do webhook
     * 
     * O Mercado Pago envia a assinatura no header X-Signature no formato:
     * sha256=hash,ts=timestamp
     * 
     * O hash é calculado como: sha256(data_id + ts + secret)
     * onde data_id é o ID do pagamento e secret é a chave secreta do webhook
     */
    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        // Validar estrutura básica do payload
        if (!isset($payload['type']) || !isset($payload['data']['id'])) {
            return false;
        }

        // ✅ SEGURANÇA: Em produção, sempre exigir assinatura
        if (empty($signature)) {
            Log::warning('Webhook sem assinatura rejeitado', [
                'payload_type' => $payload['type'] ?? null,
                'environment' => config('app.env'),
            ]);
            // Em produção, rejeitar webhooks sem assinatura
            if (!config('app.debug', false)) {
                return false;
            }
            // Em desenvolvimento, apenas logar aviso
            return config('app.debug', false);
        }

        // Obter chave secreta do webhook (prioriza painel admin, fallback .env)
        $webhookSecret = \App\Models\SystemSetting::get(
            'mercadopago.webhook_secret',
            config('services.mercadopago.webhook_secret'),
        );
        
        // ✅ SEGURANÇA: Em produção, sempre exigir webhook secret configurado
        if (empty($webhookSecret)) {
            Log::error('Webhook secret não configurado - rejeitando webhook', [
                'environment' => config('app.env'),
            ]);
            // Em produção, rejeitar se não estiver configurado
            if (!config('app.debug', false)) {
                return false;
            }
            // Em desenvolvimento, apenas validar estrutura
            Log::warning('Webhook secret não configurado - validando apenas estrutura (modo desenvolvimento)');
            return true;
        }

        // Parsear assinatura (formato: sha256=hash,ts=timestamp)
        $signatureParts = [];
        foreach (explode(',', $signature) as $part) {
            $parts = explode('=', trim($part), 2);
            if (count($parts) === 2) {
                $signatureParts[$parts[0]] = $parts[1];
            }
        }

        if (!isset($signatureParts['sha256']) || !isset($signatureParts['ts'])) {
            Log::warning('Formato de assinatura inválido', [
                'signature' => $signature,
            ]);
            return false;
        }

        $receivedHash = $signatureParts['sha256'];
        $timestamp = $signatureParts['ts'];
        $dataId = $payload['data']['id'];

        // Calcular hash esperado
        $expectedHash = hash('sha256', $dataId . $timestamp . $webhookSecret);

        // Comparar hashes (timing-safe)
        $isValid = hash_equals($expectedHash, $receivedHash);

        if (!$isValid) {
            Log::warning('Assinatura de webhook inválida', [
                'data_id' => $dataId,
                'timestamp' => $timestamp,
                'received_hash' => substr($receivedHash, 0, 10) . '...',
                'expected_hash' => substr($expectedHash, 0, 10) . '...',
            ]);
        }

        return $isValid;
    }

    /**
     * Mapeia Payment do Mercado Pago para PaymentResult
     * Adaptado para a nova estrutura da API 3.8.0+
     * Suporta tanto array quanto objeto
     */
    private function mapPaymentToResult($payment): PaymentResult
    {
        // Converter objeto para array se necessário
        if (is_object($payment)) {
            // Se tiver método toArray, usar
            if (method_exists($payment, 'toArray')) {
                $payment = $payment->toArray();
            } 
            // Se tiver método getContent, usar
            elseif (method_exists($payment, 'getContent')) {
                $payment = $payment->getContent();
            }
            // Caso contrário, converter para array
            else {
                $payment = (array) $payment;
            }
        }

        // Garantir que é array
        if (!is_array($payment)) {
            throw new DomainException('Resposta do Mercado Pago em formato inválido.');
        }

        // Converter payer de objeto para array se necessário
        $payer = $payment['payer'] ?? [];
        if (is_object($payer)) {
            if (method_exists($payer, 'toArray')) {
                $payer = $payer->toArray();
            } elseif (method_exists($payer, 'getContent')) {
                $payer = $payer->getContent();
            } else {
                $payer = (array) $payer;
            }
        }
        if (!is_array($payer)) {
            $payer = [];
        }

        $payerIdentification = is_array($payer) ? ($payer['identification'] ?? []) : [];
        // Converter identification de objeto para array se necessário
        if (is_object($payerIdentification)) {
            if (method_exists($payerIdentification, 'toArray')) {
                $payerIdentification = $payerIdentification->toArray();
            } elseif (method_exists($payerIdentification, 'getContent')) {
                $payerIdentification = $payerIdentification->getContent();
            } else {
                $payerIdentification = (array) $payerIdentification;
            }
        }
        if (!is_array($payerIdentification)) {
            $payerIdentification = [];
        }

        // Converter metadata de objeto para array se necessário
        $metadata = $payment['metadata'] ?? null;
        if ($metadata !== null && is_object($metadata)) {
            // Se for objeto do SDK do Mercado Pago, converter para array
            if (method_exists($metadata, 'toArray')) {
                $metadata = $metadata->toArray();
            } elseif (method_exists($metadata, 'getContent')) {
                $metadata = $metadata->getContent();
            } else {
                // Converter objeto genérico para array
                $metadata = (array) $metadata;
            }
        }
        // Garantir que metadata é array ou null
        if ($metadata !== null && !is_array($metadata)) {
            $metadata = null;
        }

        // Obter status_detail e mapear para mensagem amigável
        $statusDetail = $payment['status_detail'] ?? null;
        $userFriendlyMessage = null;
        
        // Se o pagamento foi rejeitado ou está pendente, criar mensagem amigável
        $status = $this->mapStatus($payment['status'] ?? 'pending');
        if ($status === 'rejected' || ($status === 'pending' && $statusDetail)) {
            $userFriendlyMessage = $this->mapStatusDetailToUserMessage($statusDetail);
        }

        // Extrair dados do PIX (QR Code) se disponível
        $pixQrCode = null;
        $pixQrCodeBase64 = null;
        $pixTicketUrl = null;
        
        // 🔥 MELHORIA: Extração ultra-robusta de dados PIX
        $poi = $payment['point_of_interaction'] ?? null;
        if (is_object($poi)) {
            if (method_exists($poi, 'toArray')) { $poi = $poi->toArray(); }
            elseif (method_exists($poi, 'getContent')) { $poi = $poi->getContent(); }
            else { $poi = (array) $poi; }
        }

        if (is_array($poi) && isset($poi['transaction_data'])) {
            $td = $poi['transaction_data'];
            if (is_object($td)) {
                if (method_exists($td, 'toArray')) { $td = $td->toArray(); }
                elseif (method_exists($td, 'getContent')) { $td = $td->getContent(); }
                else { $td = (array) $td; }
            }
            if (is_array($td)) {
                $pixQrCode = $td['qr_code'] ?? $td['qr_code_string'] ?? null;
                $pixQrCodeBase64 = $td['qr_code_base64'] ?? null;
                $pixTicketUrl = $td['ticket_url'] ?? null;
            }
        }

        return new PaymentResult(
            externalId: (string) ($payment['id'] ?? ''),
            status: $status,
            amount: \App\Domain\Shared\ValueObjects\Money::fromReais($payment['transaction_amount'] ?? 0),
            paymentMethod: $payment['payment_method_id'] ?? 'unknown',
            description: $payment['description'] ?? null,
            payerEmail: is_array($payer) ? ($payer['email'] ?? null) : null,
            payerCpf: is_array($payerIdentification) ? ($payerIdentification['number'] ?? null) : null,
            transactionId: $payment['id'] ?? null,
            errorMessage: $userFriendlyMessage ?? $statusDetail, // Usar mensagem amigável se disponível
            metadata: $metadata,
            createdAt: isset($payment['date_created']) ? new \DateTime($payment['date_created']) : null,
            approvedAt: isset($payment['date_approved']) ? new \DateTime($payment['date_approved']) : null,
            pixQrCode: $pixQrCode,
            pixQrCodeBase64: $pixQrCodeBase64,
            pixTicketUrl: $pixTicketUrl,
        );
    }

    /**
     * Mapeia status do Mercado Pago para status interno
     */
    private function mapStatus(string $mpStatus): string
    {
        return match($mpStatus) {
            'approved' => 'approved',
            'pending', 'in_process' => 'pending',
            'rejected', 'cancelled' => 'rejected',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Mapeia status_detail do Mercado Pago para mensagem amigável ao usuário
     * 
     * @param string|null $statusDetail Código do status_detail do Mercado Pago
     * @return string Mensagem amigável em português
     */
    private function mapStatusDetailToUserMessage(?string $statusDetail): string
    {
        if (empty($statusDetail)) {
            return 'Não foi possível processar o pagamento. Tente novamente ou entre em contato com o suporte.';
        }

        return match($statusDetail) {
            // Rejeições por saldo/limite
            'cc_rejected_insufficient_amount' => 'Pagamento recusado: Saldo ou limite insuficiente no cartão. Verifique o limite disponível ou tente outro cartão.',
            'cc_rejected_call_for_authorize' => 'Pagamento recusado: É necessário autorizar o pagamento com o banco emissor. Entre em contato com seu banco.',
            
            // Rejeições por dados incorretos
            'cc_rejected_bad_filled_card_number' => 'Pagamento recusado: Número do cartão inválido. Verifique os dados do cartão.',
            'cc_rejected_bad_filled_date' => 'Pagamento recusado: Data de validade do cartão inválida. Verifique a data de expiração.',
            'cc_rejected_bad_filled_other' => 'Pagamento recusado: Dados do cartão incorretos. Verifique todas as informações.',
            'cc_rejected_bad_filled_security_code' => 'Pagamento recusado: Código de segurança (CVV) inválido. Verifique o código de segurança do cartão.',
            
            // Rejeições por cartão
            'cc_rejected_card_error' => 'Pagamento recusado: Erro no cartão. Verifique se o cartão está ativo ou tente outro cartão.',
            'cc_rejected_card_disabled' => 'Pagamento recusado: Cartão desabilitado. Entre em contato com o banco emissor.',
            'cc_rejected_card_number_error' => 'Pagamento recusado: Número do cartão inválido. Verifique o número do cartão.',
            'cc_rejected_duplicated_payment' => 'Pagamento recusado: Este pagamento já foi processado anteriormente.',
            'cc_rejected_high_risk' => 'Pagamento recusado: Transação considerada de alto risco. Tente novamente ou use outro método de pagamento.',
            'cc_rejected_insufficient_data' => 'Pagamento recusado: Dados insuficientes. Verifique todas as informações do cartão.',
            'cc_rejected_invalid_installments' => 'Pagamento recusado: Número de parcelas inválido para este cartão.',
            'cc_rejected_max_attempts' => 'Pagamento recusado: Muitas tentativas. Aguarde alguns minutos e tente novamente.',
            
            // Rejeições por conta/banco
            'cc_rejected_other_reason' => 'Pagamento recusado pelo banco emissor. Entre em contato com seu banco para mais informações.',
            'cc_rejected_blacklist' => 'Pagamento recusado: Cartão não autorizado. Entre em contato com o suporte.',
            
            // Pendências
            'pending_contingency' => 'Pagamento pendente: Estamos analisando sua transação. Você será notificado em breve.',
            'pending_review_manual' => 'Pagamento pendente: Sua transação está sendo revisada. Você será notificado em breve.',
            'pending_waiting_transfer' => 'Aguardando pagamento do PIX. Finalize a transferência no seu app de banco usando o QR Code.',
            'pending_waiting_payment' => 'Aguardando pagamento. Conclua o pagamento para aprovar a transação.',
            'pending_capture' => 'Pagamento autorizado, aguardando captura.',
            
            // Erros gerais
            'cc_rejected' => 'Pagamento recusado pelo banco emissor. Verifique os dados do cartão ou tente outro método de pagamento.',
            
            default => "Pagamento recusado: {$statusDetail}. Entre em contato com o suporte se o problema persistir.",
        };
    }

    /**
     * Cria um Customer no Mercado Pago e salva o cartão
     * 
     * 🔥 MELHORIA: External Vaulting - Retorna apenas customer_id e card_id (não são dados sensíveis)
     * 
     * @param string $email Email do cliente
     * @param string $cardToken Token do cartão (gerado pelo frontend)
     * @param string|null $cpf CPF do cliente (opcional)
     * @return array ['customer_id' => string, 'card_id' => string]
     * @throws DomainException Em caso de erro
     */
    public function createCustomerAndCard(string $email, string $cardToken, ?string $cpf = null): array
    {
        $this->initialize();

        try {
            // Criar Customer no Mercado Pago
            $customerClient = new \MercadoPago\Client\Customer\CustomerClient();
            
            $customerData = [
                'email' => $email,
            ];

            if ($cpf) {
                $cpfLimpo = preg_replace('/\D/', '', $cpf);
                if (strlen($cpfLimpo) === 11) {
                    $customerData['identification'] = [
                        'type' => 'CPF',
                        'number' => $cpfLimpo,
                    ];
                }
            }

            $customer = $customerClient->create($customerData);

            // Converter para array se necessário
            if (is_object($customer)) {
                if (method_exists($customer, 'toArray')) {
                    $customer = $customer->toArray();
                } elseif (method_exists($customer, 'getContent')) {
                    $customer = $customer->getContent();
                } else {
                    $customer = (array) $customer;
                }
            }

            if (!is_array($customer) || !isset($customer['id'])) {
                throw new DomainException('Erro ao criar Customer no Mercado Pago: resposta inválida.');
            }

            $customerId = (string) $customer['id'];

            // Salvar cartão no Customer (SDK dx-php: CustomerCardClient, não Card\CardClient)
            $cardClient = new CustomerCardClient();
            
            $cardData = [
                'token' => $cardToken,
            ];

            $card = $cardClient->create($customerId, $cardData);

            // Converter para array se necessário
            if (is_object($card)) {
                if (method_exists($card, 'toArray')) {
                    $card = $card->toArray();
                } elseif (method_exists($card, 'getContent')) {
                    $card = $card->getContent();
                } else {
                    $card = (array) $card;
                }
            }

            if (!is_array($card) || !isset($card['id'])) {
                throw new DomainException('Erro ao salvar cartão no Mercado Pago: resposta inválida.');
            }

            $cardId = (string) $card['id'];

            Log::info('Customer e Card criados no Mercado Pago', [
                'customer_id' => $customerId,
                'card_id' => $cardId,
                'email' => $email,
            ]);

            return [
                'customer_id' => $customerId,
                'card_id' => $cardId,
            ];

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $content = $apiResponse ? $apiResponse->getContent() : null;
            
            $errorMessage = 'Erro ao criar Customer/Card no Mercado Pago';
            if ($content && isset($content['message'])) {
                $errorMessage = $content['message'];
            }

            Log::error('Erro ao criar Customer/Card no Mercado Pago', [
                'exception' => $e->getMessage(),
                'api_response' => $content,
                'email' => $email,
            ]);

            throw new DomainException("Erro ao salvar método de pagamento: {$errorMessage}");
        } catch (\Exception $e) {
            Log::error('Exceção ao criar Customer/Card no Mercado Pago', [
                'exception' => $e->getMessage(),
                'email' => $email,
            ]);

            throw new DomainException("Erro ao salvar método de pagamento: {$e->getMessage()}");
        }
    }

    /**
     * Gera um card_token novo a partir de um card_id já salvo no Customer.
     *
     * O MP exige essa regeneração a cada pagamento por razões de segurança
     * (evita replay). Usa a public_key na query string — é a única operação
     * do SDK que autentica por public_key em vez de access_token.
     *
     * @throws DomainException se o MP recusar.
     */
    private function generateCardTokenFromSavedCard(string $publicKey, string $cardId, ?string $cvv = null): string
    {
        $url = 'https://api.mercadopago.com/v1/card_tokens?public_key=' . urlencode($publicKey);
        $payload = ['card_id' => $cardId];
        if (!empty($cvv)) {
            $payload['security_code'] = $cvv;
        }
        $body = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error('generateCardTokenFromSavedCard: erro de rede', [
                'card_id' => $cardId,
                'error' => $err,
            ]);
            throw new DomainException('Erro de rede ao regenerar card token: ' . $err);
        }

        $decoded = json_decode((string) $response, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['id'])) {
            Log::error('generateCardTokenFromSavedCard: MP recusou', [
                'card_id' => $cardId,
                'http_status' => $status,
                'response' => $decoded,
            ]);
            $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? 'resposta inválida') : 'resposta inválida';
            throw new DomainException("Falha ao regenerar card token: {$msg}");
        }

        return (string) $decoded['id'];
    }

    /**
     * Processa um pagamento usando um card_id salvo (one-click buy)
     *
     * @param PaymentRequest $request Dados do pagamento (sem cardToken)
     * @param string $customerId ID do Customer no Mercado Pago
     * @param string $cardId ID do Cartão salvo no Mercado Pago
     * @param string $idempotencyKey Chave de idempotência
     * @return PaymentResult Resultado do pagamento
     * @throws DomainException Em caso de erro
     */
    public function processPaymentWithSavedCard(
        PaymentRequest $request,
        string $customerId,
        string $cardId,
        string $idempotencyKey
    ): PaymentResult {
        $this->initialize();

        try {
            // 🔥 MP exige regenerar um card_token a cada cobrança, mesmo com
            // cartão salvo (Customer+Card). Passar o card_id direto como token
            // devolve 404 "Card Token not found".
            //
            // Fluxo oficial (one-click): POST /v1/card_tokens { card_id }
            // com a public_key → retorna novo token → usar no /v1/payments.
            $publicKey = \App\Models\SystemSetting::get(
                'mercadopago.public_key',
                config('services.mercadopago.public_key'),
            );

            if (empty($publicKey)) {
                throw new DomainException('Public Key do Mercado Pago não configurada — necessária para regenerar card token.');
            }

            // Se o request trouxer CVV (pagamento assistido), regeneramos com
            // security_code — evita o erro "security_code_id can't be null"
            // do MP em cobranças avulsas via /v1/payments.
            $cvv = $request->metadata['security_code'] ?? null;
            $freshToken = $this->generateCardTokenFromSavedCard($publicKey, $cardId, $cvv);

            // Preparar dados do pagamento usando o token recém-gerado
            $paymentData = [
                'transaction_amount' => (float) round($request->amount->toReais(), 2),
                'description' => substr($request->description, 0, 255),
                'payer' => [
                    'type' => 'customer',
                    'id' => $customerId,
                ],
                'token' => $freshToken,
                'installments' => (int) ($request->installments ?? 1),
            ];

            // External reference
            if ($request->externalReference) {
                $paymentData['external_reference'] = substr($request->externalReference, 0, 256);
            } else {
                $paymentData['external_reference'] = substr($idempotencyKey, 0, 256);
            }

            // Metadados
            if ($request->metadata && is_array($request->metadata)) {
                $paymentData['metadata'] = $request->metadata;
            }

            $paymentData['statement_descriptor'] = 'SISTEMA ROMULO';

            Log::info('Processando pagamento com cartão salvo', [
                'customer_id' => $customerId,
                'card_id' => $cardId,
                'amount' => $paymentData['transaction_amount'],
                'idempotency_key' => $idempotencyKey,
            ]);

            // Criar pagamento
            $payment = $this->paymentClient->create($paymentData);

            // Verificar erros
            if (is_array($payment) && isset($payment['error'])) {
                $errorMessage = $payment['error']['message'] ?? 'Erro desconhecido no pagamento';
                Log::error('Erro ao processar pagamento com cartão salvo', [
                    'error' => $payment['error'],
                    'customer_id' => $customerId,
                    'card_id' => $cardId,
                ]);
                throw new DomainException("Erro no pagamento: {$errorMessage}");
            }

            return $this->mapPaymentToResult($payment);

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $content = $apiResponse ? $apiResponse->getContent() : null;
            
            $errorMessage = 'Erro ao processar pagamento com cartão salvo';
            if ($content && isset($content['message'])) {
                $errorMessage = $content['message'];
            }

            Log::error('Erro ao processar pagamento com cartão salvo', [
                'exception' => $e->getMessage(),
                'api_response' => $content,
                'customer_id' => $customerId,
                'card_id' => $cardId,
            ]);

            throw new DomainException("Erro ao processar pagamento: {$errorMessage}");
        } catch (\Exception $e) {
            Log::error('Exceção ao processar pagamento com cartão salvo', [
                'exception' => $e->getMessage(),
                'customer_id' => $customerId,
                'card_id' => $cardId,
            ]);

            throw new DomainException("Erro ao processar pagamento: {$e->getMessage()}");
        }
    }
}
