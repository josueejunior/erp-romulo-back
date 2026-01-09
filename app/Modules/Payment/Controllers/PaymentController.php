<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Payment\UseCases\ProcessarAssinaturaPlanoUseCase;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Http\Requests\Payment\ProcessarAssinaturaRequest;
use App\Models\Tenant;
use App\Models\AfiliadoReferencia;
use App\Modules\Assinatura\Models\Assinatura;
use App\Modules\Afiliado\Models\Afiliado;
use App\Application\Afiliado\UseCases\ValidarCupomAfiliadoUseCase;
use App\Application\Afiliado\UseCases\RastrearReferenciaAfiliadoUseCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Controller para processamento de pagamentos
 */
class PaymentController extends BaseApiController
{
    public function __construct(
        private ProcessarAssinaturaPlanoUseCase $processarAssinaturaUseCase,
        private PlanoRepositoryInterface $planoRepository,
        private CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private ValidarCupomAfiliadoUseCase $validarCupomAfiliadoUseCase,
        private RastrearReferenciaAfiliadoUseCase $rastrearReferenciaAfiliadoUseCase,
    ) {}

    /**
     * Processa assinatura de plano
     * Usa Form Request para validação
     * 
     * POST /api/payments/processar-assinatura
     */
    public function processarAssinatura(ProcessarAssinaturaRequest $request)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Buscar tenant atual
            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Buscar plano usando repository DDD
            $plano = $this->planoRepository->buscarModeloPorId($validated['plano_id']);
            if (!$plano) {
                return response()->json(['message' => 'Plano não encontrado'], 404);
            }
            if (!$plano->isAtivo()) {
                return response()->json(['message' => 'Plano não está ativo'], 400);
            }

            // Calcular valor
            $valor = $validated['periodo'] === 'anual' 
                ? $plano->preco_anual 
                : $plano->preco_mensal;

            // Aplicar cupom se fornecido
            $cupomAplicado = null;
            $cupomAfiliadoAplicado = null;
            $referenciaAfiliado = null;
            $valorDesconto = 0;
            
            if (isset($validated['cupom_codigo'])) {
                $codigoCupom = strtoupper(trim($validated['cupom_codigo']));
                
                // 1. Primeiro, verificar se é código de afiliado válido
                $afiliadoPorCodigo = Afiliado::where('codigo', $codigoCupom)
                    ->where('ativo', true)
                    ->first();
                
                if ($afiliadoPorCodigo) {
                    // É um código de afiliado - validar e aplicar
                    try {
                        $cupomAfiliado = $this->validarCupomAfiliadoUseCase->executar($codigoCupom);
                        
                        if ($cupomAfiliado && $cupomAfiliado['valido']) {
                            // Validar se CNPJ já usou cupom (uso único por CNPJ)
                            $cnpjTenant = $tenant->cnpj;
                            if ($cnpjTenant) {
                                $jaUsouCupom = $this->rastrearReferenciaAfiliadoUseCase->cnpjJaUsouCupom($cnpjTenant);
                                
                                if ($jaUsouCupom) {
                                    return response()->json([
                                        'message' => 'Este CNPJ já utilizou um cupom de afiliado. O cupom é de uso único por CNPJ.',
                                    ], 422);
                                }
                            }
                            
                            // Buscar referência do afiliado vinculada ao tenant
                            $referenciaAfiliado = AfiliadoReferencia::where('tenant_id', $tenant->id)
                                ->where(function($query) use ($cupomAfiliado, $codigoCupom) {
                                    $query->where('afiliado_id', $cupomAfiliado['afiliado_id'])
                                          ->orWhere('referencia_code', $codigoCupom);
                                })
                                ->where('cadastro_concluido', true)
                                ->where('cupom_aplicado', false)
                                ->orderBy('cadastro_concluido_em', 'desc')
                                ->first();
                            
                            if (!$referenciaAfiliado) {
                                return response()->json([
                                    'message' => 'Este cupom não está disponível para este cliente ou já foi utilizado.',
                                ], 422);
                            }
                            
                            // Calcular desconto do afiliado
                            $percentualDesconto = $cupomAfiliado['percentual_desconto'] ?? 30;
                            $valorDesconto = ($valor * $percentualDesconto) / 100;
                            $valor = max(0, $valor - $valorDesconto);
                            $cupomAfiliadoAplicado = $cupomAfiliado;
                            
                            Log::info('Cupom de afiliado aplicado com sucesso', [
                                'cupom' => $codigoCupom,
                                'afiliado_id' => $cupomAfiliado['afiliado_id'],
                                'desconto_percentual' => $percentualDesconto,
                                'desconto' => $valorDesconto,
                                'valor_final' => $valor,
                            ]);
                        }
                    } catch (\DomainException $e) {
                        // Erro na validação do cupom de afiliado
                        return response()->json([
                            'message' => $e->getMessage(),
                        ], 422);
                    }
                } else {
                    // 2. Não é código de afiliado - tentar como cupom normal
                    $cupom = \App\Modules\Assinatura\Models\Cupom::where('codigo', $codigoCupom)->first();
                    
                    if ($cupom) {
                        $validacao = $cupom->podeSerUsadoPor($tenant->id, $plano->id, $valor);
                        
                        if ($validacao['valido']) {
                            $valorDesconto = $cupom->calcularDesconto($valor);
                            $valor = max(0, $valor - $valorDesconto);
                            $cupomAplicado = $cupom;
                            
                            Log::info('Cupom normal aplicado com sucesso', [
                                'cupom' => $cupom->codigo,
                                'desconto' => $valorDesconto,
                                'valor_final' => $valor,
                            ]);
                        } else {
                            return response()->json([
                                'message' => $validacao['motivo']
                            ], 400);
                        }
                    } else {
                        // Cupom não encontrado (nem afiliado nem normal)
                        return response()->json([
                            'message' => 'Cupom não encontrado ou inválido.',
                        ], 404);
                    }
                }
            }

            $isGratis = ($valor === null || $valor == 0);

            // Se for gratuito, criar assinatura diretamente sem passar pelo gateway
            if ($isGratis) {
                // 🔥 CRÍTICO: Garantir que o tenancy está inicializado para o tenant correto
                if (!tenancy()->initialized || tenancy()->tenant->id !== $tenant->id) {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                    tenancy()->initialize($tenant);
                }

                // Buscar assinatura gratuita existente para o MESMO plano
                $assinaturaModel = Assinatura::where('tenant_id', $tenant->id)
                    ->where('plano_id', $plano->id)
                    ->where('status', 'ativa')
                    ->first();

                if (!$assinaturaModel) {
                    // CRÍTICO: Cancelar assinaturas ativas antigas antes de criar a nova
                    $assinaturasAntigas = Assinatura::where('tenant_id', $tenant->id)
                        ->where('status', 'ativa')
                        ->get();
                    
                    foreach ($assinaturasAntigas as $assinaturaAntiga) {
                        $assinaturaAntiga->update([
                            'status' => 'cancelada',
                            'data_cancelamento' => now(),
                            'observacoes' => ($assinaturaAntiga->observacoes ?? '') . 
                                "\n\nCancelada automaticamente por troca de plano em " . now()->format('d/m/Y H:i:s'),
                        ]);
                        
                        Log::info('Assinatura antiga cancelada por troca de plano gratuito', [
                            'assinatura_antiga_id' => $assinaturaAntiga->id,
                            'plano_antigo_id' => $assinaturaAntiga->plano_id,
                            'tenant_id' => $tenant->id,
                        ]);
                    }

                    // Criar nova assinatura gratuita usando Use Case (garante tenancy correto)
                    $dataInicio = Carbon::now();
                    $dataFim = $dataInicio->copy()->addDays(14); // Trial de 14 dias

                    // 🔥 CRÍTICO: Obter usuário autenticado
                    $user = auth()->user();
                    if (!$user) {
                        return response()->json(['message' => 'Usuário não autenticado'], 401);
                    }

                    $assinaturaDTO = new CriarAssinaturaDTO(
                        userId: $user->id, // 🔥 NOVO: Assinatura pertence ao usuário
                        planoId: $plano->id,
                        status: 'ativa',
                        dataInicio: $dataInicio,
                        dataFim: $dataFim,
                        valorPago: 0,
                        metodoPagamento: 'gratuito',
                        transacaoId: null,
                        diasGracePeriod: 0,
                        observacoes: 'Assinatura gratuita (Trial)',
                        tenantId: $tenant->id, // Opcional para compatibilidade
                    );

                    $assinaturaDomain = $this->criarAssinaturaUseCase->executar($assinaturaDTO);
                    $assinaturaModel = Assinatura::find($assinaturaDomain->id);
                }
                
                // Se cupom de afiliado foi aplicado em plano gratuito, marcar flag
                if ($cupomAfiliadoAplicado && $referenciaAfiliado) {
                    $referenciaAfiliado->update([
                        'cupom_aplicado' => true,
                    ]);
                    
                    Log::info('Flag cupom_aplicado marcada na referência de afiliado (plano gratuito)', [
                        'referencia_id' => $referenciaAfiliado->id,
                        'afiliado_id' => $cupomAfiliadoAplicado['afiliado_id'],
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $assinaturaModel->id,
                    ]);
                }

                Log::info('Assinatura gratuita criada e vinculada ao tenant', [
                    'tenant_id' => $tenant->id,
                    'assinatura_id' => $assinaturaModel->id,
                    'plano_id' => $plano->id,
                ]);

                return response()->json([
                    'message' => 'Assinatura gratuita ativada com sucesso',
                    'data' => [
                        'assinatura_id' => $assinaturaModel->id,
                        'status' => $assinaturaModel->status,
                        'data_fim' => $assinaturaModel->data_fim->format('Y-m-d'),
                    ],
                ], 201);
            }

            // Para planos pagos, criar PaymentRequest e processar via gateway
            $paymentMethod = $validated['payment_method'] ?? 'credit_card';
            
            $paymentRequestData = [
                'amount' => $valor,
                'description' => "Plano {$plano->nome} - {$validated['periodo']} - Sistema Rômulo",
                'payer_email' => $validated['payer_email'],
                'payer_cpf' => $validated['payer_cpf'] ?? null,
                'payment_method_id' => $paymentMethod === 'pix' ? 'pix' : null, // Para PIX, enviar explicitamente
                'external_reference' => "tenant_{$tenant->id}_plano_{$plano->id}",
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plano_id' => $plano->id,
                    'periodo' => $validated['periodo'],
                ],
            ];

            // Para cartão, adicionar token e parcelas
            if ($paymentMethod === 'credit_card') {
                $paymentRequestData['card_token'] = $validated['card_token'] ?? null;
                // Installments só é necessário para cartão, e pode não estar no validated se não foi enviado
                $paymentRequestData['installments'] = isset($validated['installments']) ? (int) $validated['installments'] : 1;
                // NÃO enviar payment_method_id - será detectado automaticamente do token
                unset($paymentRequestData['payment_method_id']);
            }
            // Para PIX, não adicionar installments - PaymentRequest::fromArray usa valor padrão 1

            $paymentRequest = PaymentRequest::fromArray($paymentRequestData);

            // Processar assinatura
            $assinatura = $this->processarAssinaturaUseCase->executar(
                $tenant,
                $plano,
                $paymentRequest,
                $validated['periodo']
            );

            // Registrar uso do cupom se aplicado (apenas se assinatura foi criada como ativa)
            // Se estiver pendente, será marcado no webhook quando pagamento for confirmado
            if ($assinatura->status === 'ativa') {
                // Cupom normal
                if ($cupomAplicado && $valorDesconto > 0) {
                    \App\Modules\Assinatura\Models\CupomUso::create([
                        'cupom_id' => $cupomAplicado->id,
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $assinatura->id,
                        'valor_desconto_aplicado' => $valorDesconto,
                        'valor_original' => $valor + $valorDesconto,
                        'valor_final' => $valor,
                        'usado_em' => now(),
                    ]);

                    // Incrementar contador de uso
                    $cupomAplicado->increment('total_usado');
                }
                
                // Cupom de afiliado - marcar flag cupom_aplicado na referência
                if ($cupomAfiliadoAplicado && $referenciaAfiliado && $valorDesconto > 0) {
                    $referenciaAfiliado->update([
                        'cupom_aplicado' => true,
                    ]);
                    
                    Log::info('Flag cupom_aplicado marcada na referência de afiliado (pagamento aprovado)', [
                        'referencia_id' => $referenciaAfiliado->id,
                        'afiliado_id' => $cupomAfiliadoAplicado['afiliado_id'],
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $assinatura->id,
                    ]);
                }
            } else {
                // Se pendente, salvar referência para marcar depois no webhook
                // Isso será feito no WebhookController quando pagamento for confirmado
                Log::info('Cupom de afiliado aplicado mas pagamento pendente - será marcado no webhook', [
                    'referencia_id' => $referenciaAfiliado?->id,
                    'afiliado_id' => $cupomAfiliadoAplicado['afiliado_id'] ?? null,
                    'tenant_id' => $tenant->id,
                    'assinatura_id' => $assinatura->id,
                ]);
            }

            // Buscar resultado do pagamento para incluir dados do PIX se disponível
            $paymentLog = \App\Models\PaymentLog::where('external_id', $assinatura->transacao_id)->first();
            $responseData = [
                'assinatura_id' => $assinatura->id,
                'status' => $assinatura->status,
                'data_fim' => $assinatura->data_fim->format('Y-m-d'),
            ];

            // Determinar mensagem baseada no status
            $isPendente = $assinatura->status === 'suspensa' || $assinatura->status === 'pendente';
            $message = $isPendente 
                ? 'Pagamento em análise. Você será notificado quando for aprovado.' 
                : 'Assinatura processada com sucesso';

            // Se for PIX e estiver pendente, incluir dados do QR Code
            if ($paymentMethod === 'pix' && $isPendente) {
                $dadosResposta = $paymentLog->dados_resposta ?? [];
                if (isset($dadosResposta['pix_qr_code_base64']) || isset($dadosResposta['pix_qr_code'])) {
                    $responseData['pix_qr_code_base64'] = $dadosResposta['pix_qr_code_base64'] ?? null;
                    $responseData['pix_qr_code'] = $dadosResposta['pix_qr_code'] ?? null;
                    $responseData['pix_ticket_url'] = $dadosResposta['pix_ticket_url'] ?? null;
                    $responseData['payment_id'] = $paymentLog->external_id ?? null;
                }
            }

            // Se estiver pendente, incluir informação adicional
            if ($isPendente && $paymentLog) {
                $dadosResposta = $paymentLog->dados_resposta ?? [];
                if (isset($dadosResposta['error_message'])) {
                    $responseData['pending_reason'] = $dadosResposta['error_message'];
                }
            }

            return response()->json([
                'message' => $message,
                'data' => $responseData,
                'pending' => $isPendente, // Flag para o frontend saber que está pendente
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao processar assinatura', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar assinatura: ' . $e->getMessage(),
            ], 500);
        }
    }
}


