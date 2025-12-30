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
            // Validar token do cartão
            if (empty($request->cardToken)) {
                throw new DomainException('Token do cartão é obrigatório para processar o pagamento.');
            }

            // Preparar dados do pagamento para a nova API
            $paymentData = [
                'transaction_amount' => round($request->amount->toReais(), 2), // Garantir 2 casas decimais
                'description' => $request->description,
                'installments' => (int) ($request->installments ?? 1),
                'payment_method_id' => $request->paymentMethodId ?? 'credit_card',
                'token' => $request->cardToken, // Token é obrigatório
                'payer' => [
                    'email' => $request->payerEmail,
                ],
                'external_reference' => $request->externalReference ?? $idempotencyKey,
            ];

            // CPF do pagador (obrigatório para alguns casos)
            if ($request->payerCpf) {
                $cpfLimpo = preg_replace('/\D/', '', $request->payerCpf);
                if (strlen($cpfLimpo) === 11) {
                    $paymentData['payer']['identification'] = [
                        'type' => 'CPF',
                        'number' => $cpfLimpo,
                    ];
                }
            }

            // Metadados
            if ($request->metadata) {
                $paymentData['metadata'] = $request->metadata;
            }

            // Log dos dados antes de enviar (sem dados sensíveis)
            Log::debug('Dados do pagamento sendo enviados ao Mercado Pago', [
                'transaction_amount' => $paymentData['transaction_amount'],
                'installments' => $paymentData['installments'],
                'payment_method_id' => $paymentData['payment_method_id'],
                'has_token' => !empty($paymentData['token']),
                'token_length' => strlen($paymentData['token']),
                'payer_email' => $paymentData['payer']['email'],
                'has_cpf' => isset($paymentData['payer']['identification']),
            ]);

            // Criar pagamento usando a nova API
            $payment = $this->paymentClient->create($paymentData);

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

        $payer = $payment['payer'] ?? [];
        $payerIdentification = is_array($payer) ? ($payer['identification'] ?? []) : [];

        return new PaymentResult(
            externalId: (string) ($payment['id'] ?? ''),
            status: $this->mapStatus($payment['status'] ?? 'pending'),
            amount: \App\Domain\Shared\ValueObjects\Money::fromReais($payment['transaction_amount'] ?? 0),
            paymentMethod: $payment['payment_method_id'] ?? 'unknown',
            description: $payment['description'] ?? null,
            payerEmail: is_array($payer) ? ($payer['email'] ?? null) : null,
            payerCpf: is_array($payerIdentification) ? ($payerIdentification['number'] ?? null) : null,
            transactionId: $payment['id'] ?? null,
            errorMessage: $payment['status_detail'] ?? null,
            metadata: $payment['metadata'] ?? null,
            createdAt: isset($payment['date_created']) ? new \DateTime($payment['date_created']) : null,
            approvedAt: isset($payment['date_approved']) ? new \DateTime($payment['date_approved']) : null,
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
}
