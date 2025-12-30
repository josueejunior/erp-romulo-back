<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoClient;

/**
 * Implementação do gateway Mercado Pago
 * 
 * Infrastructure Layer - Responsável pela comunicação com o Mercado Pago
 * Compatível com SDK versão 3.8.0+
 */
class MercadoPagoGateway implements PaymentProviderInterface
{
    private string $accessToken;
    private bool $isSandbox;
    private PaymentClient $paymentClient;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        $this->isSandbox = config('services.mercadopago.sandbox', true);

        if (empty($this->accessToken)) {
            throw new DomainException('Mercado Pago access token não configurado. Configure MP_ACCESS_TOKEN no arquivo .env');
        }

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
    }

    /**
     * Processa um pagamento
     */
    public function processPayment(PaymentRequest $request, string $idempotencyKey): PaymentResult
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

            // CPF do pagador (obrigatório para cartão, recomendado para PIX)
            if ($request->payerCpf) {
                $cpfLimpo = preg_replace('/\D/', '', $request->payerCpf);
                if (strlen($cpfLimpo) === 11) {
                    $paymentData['payer']['identification'] = [
                        'type' => 'CPF',
                        'number' => $cpfLimpo,
                    ];
                }
            } else {
                if ($isCartao) {
                    Log::warning('CPF do pagador não fornecido para pagamento com cartão', [
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
                if (str_contains(strtolower($detailedMessage), 'unauthorized') || str_contains(strtolower($detailedMessage), 'policy')) {
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
     */
    public function getPaymentStatus(string $externalId): PaymentResult
    {
        try {
            $payment = $this->paymentClient->get($externalId);

            if (!$payment || isset($payment['error'])) {
                throw new NotFoundException("Pagamento não encontrado: {$externalId}");
            }

            return $this->mapPaymentToResult($payment);

        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erro ao consultar status do pagamento no Mercado Pago', [
                'external_id' => $externalId,
                'exception' => $e->getMessage(),
            ]);

            throw new DomainException("Erro ao consultar pagamento: {$e->getMessage()}");
        }
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
     */
    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        // TODO: Implementar validação de assinatura HMAC se necessário
        // Por enquanto, validar apenas se o payload tem estrutura esperada
        return isset($payload['type']) && isset($payload['data']['id']);
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
        
        $pointOfInteraction = $payment['point_of_interaction'] ?? null;
        if ($pointOfInteraction) {
            // Converter objeto para array se necessário
            if (is_object($pointOfInteraction)) {
                if (method_exists($pointOfInteraction, 'toArray')) {
                    $pointOfInteraction = $pointOfInteraction->toArray();
                } elseif (method_exists($pointOfInteraction, 'getContent')) {
                    $pointOfInteraction = $pointOfInteraction->getContent();
                } else {
                    $pointOfInteraction = (array) $pointOfInteraction;
                }
            }
            
            if (is_array($pointOfInteraction)) {
                $transactionData = $pointOfInteraction['transaction_data'] ?? [];
                if (is_object($transactionData)) {
                    if (method_exists($transactionData, 'toArray')) {
                        $transactionData = $transactionData->toArray();
                    } elseif (method_exists($transactionData, 'getContent')) {
                        $transactionData = $transactionData->getContent();
                    } else {
                        $transactionData = (array) $transactionData;
                    }
                }
                
                if (is_array($transactionData)) {
                    $pixQrCode = $transactionData['qr_code'] ?? null;
                    $pixQrCodeBase64 = $transactionData['qr_code_base64'] ?? null;
                    $pixTicketUrl = $transactionData['ticket_url'] ?? null;
                }
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
            
            // Erros gerais
            'cc_rejected' => 'Pagamento recusado pelo banco emissor. Verifique os dados do cartão ou tente outro método de pagamento.',
            
            default => "Pagamento recusado: {$statusDetail}. Entre em contato com o suporte se o problema persistir.",
        };
    }
}
