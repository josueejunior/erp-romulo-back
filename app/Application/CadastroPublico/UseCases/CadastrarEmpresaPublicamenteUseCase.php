<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\UseCases;

use App\Application\CadastroPublico\DTOs\CadastroPublicoDTO;
use App\Application\CadastroPublico\Services\ValidarDuplicidadesService;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Application\Payment\UseCases\ProcessarAssinaturaPlanoUseCase;
use App\Application\Empresa\UseCases\RegistrarAfiliadoNaEmpresaUseCase;
use App\Application\Afiliado\UseCases\ValidarCupomAfiliadoUseCase;
use App\Application\Afiliado\UseCases\RastrearReferenciaAfiliadoUseCase;
use App\Application\Afiliado\UseCases\CriarIndicacaoAfiliadoUseCase;
use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use App\Application\Onboarding\DTOs\IniciarOnboardingDTO;
use App\Application\Onboarding\DTOs\ConcluirOnboardingDTO;
use App\Domain\Assinatura\Services\AssinaturaDomainService;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\EmailJaCadastradoException;
use App\Domain\Exceptions\CnpjJaCadastradoException;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Tenant\Events\EmpresaCriada;
use App\Jobs\VerificarPagamentoPendenteJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Use Case: Cadastrar Empresa Publicamente
 * 
 * Orquestra todo o fluxo de cadastro público:
 * - Valida duplicidades
 * - Cria tenant e empresa
 * - Cria usuário admin
 * - Processa pagamento (se necessário)
 * - Cria assinatura
 * - Registra afiliado (se aplicável)
 * 
 * Este Use Case centraliza TODA a lógica de orquestração,
 * removendo responsabilidades do Controller.
 */
final class CadastrarEmpresaPublicamenteUseCase
{
    public function __construct(
        private readonly CriarTenantUseCase $criarTenantUseCase,
        private readonly CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private readonly ProcessarAssinaturaPlanoUseCase $processarAssinaturaPlanoUseCase,
        private readonly RegistrarAfiliadoNaEmpresaUseCase $registrarAfiliadoNaEmpresaUseCase,
        private readonly ValidarCupomAfiliadoUseCase $validarCupomAfiliadoUseCase,
        private readonly RastrearReferenciaAfiliadoUseCase $rastrearReferenciaAfiliadoUseCase,
        private readonly CriarIndicacaoAfiliadoUseCase $criarIndicacaoAfiliadoUseCase,
        private readonly GerenciarOnboardingUseCase $gerenciarOnboardingUseCase,
        private readonly AssinaturaDomainService $assinaturaDomainService,
        private readonly ValidarDuplicidadesService $validarDuplicidadesService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly PlanoRepositoryInterface $planoRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Executa o cadastro público completo
     * 
     * @return array{
     *   tenant: \App\Models\Tenant,
     *   empresa: \App\Domain\Empresa\Entities\Empresa,
     *   admin_user: \App\Modules\Auth\Models\User,
     *   assinatura: \App\Modules\Assinatura\Models\Assinatura,
     *   plano: \App\Modules\Assinatura\Models\Plano,
     *   data_fim: \Carbon\Carbon,
     *   payment_result?: array
     * }
     */
    public function executar(CadastroPublicoDTO $dto): array
    {
        // Gerar correlation ID para rastreamento
        $correlationId = Str::uuid()->toString();
        $startTime = microtime(true);
        
        Log::info('CadastrarEmpresaPublicamenteUseCase::executar iniciado', [
            'correlation_id' => $correlationId,
            'idempotency_key' => $dto->idempotencyKey,
            'plano_id' => $dto->planoId,
            'razao_social' => $dto->razaoSocial,
            'admin_email' => $dto->adminEmail,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Verificar idempotência (se houver chave)
        if ($dto->idempotencyKey) {
            $cacheKey = "cadastro:idempotency:{$dto->idempotencyKey}";
            $existing = Cache::get($cacheKey);
            
            if ($existing) {
                Log::info('CadastrarEmpresaPublicamenteUseCase - Retornando resultado idempotente', [
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $dto->idempotencyKey,
                    'cached_at' => $existing['cached_at'] ?? null,
                ]);
                return $existing['result'];
            }
        }

        try {
            // 0. Rastrear referência de afiliado (se houver)
            $referenciaAfiliado = null;
            if ($dto->referenciaAfiliado) {
                $referenciaAfiliado = $this->rastrearReferenciaAfiliadoUseCase->executar(
                    referenciaCode: $dto->referenciaAfiliado,
                    sessionId: $dto->sessionId,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent(),
                    email: $dto->adminEmail,
                );
                
                // Se encontrou referência e não tem afiliação no DTO, aplicar automaticamente
                if ($referenciaAfiliado && !$dto->afiliacao) {
                    Log::info('CadastrarEmpresaPublicamenteUseCase - Aplicando cupom automático via referência', [
                        'afiliado_id' => $referenciaAfiliado->afiliado_id,
                        'referencia_code' => $dto->referenciaAfiliado,
                    ]);
                    
                    // Verificar se CNPJ já usou cupom
                    if ($dto->cnpj && $this->rastrearReferenciaAfiliadoUseCase->cnpjJaUsouCupom($dto->cnpj)) {
                        Log::warning('CadastrarEmpresaPublicamenteUseCase - CNPJ já usou cupom anteriormente', [
                            'cnpj' => $dto->cnpj,
                        ]);
                    } else {
                        // Buscar afiliado para criar DTO de afiliação
                        $afiliado = $referenciaAfiliado->afiliado;
                        if ($afiliado) {
                            // Criar AfiliacaoDTO automaticamente
                            $dto = new \App\Application\CadastroPublico\DTOs\CadastroPublicoDTO(
                                planoId: $dto->planoId,
                                periodo: $dto->periodo,
                                razaoSocial: $dto->razaoSocial,
                                cnpj: $dto->cnpj,
                                email: $dto->email,
                                endereco: $dto->endereco,
                                cidade: $dto->cidade,
                                estado: $dto->estado,
                                cep: $dto->cep,
                                telefones: $dto->telefones,
                                logo: $dto->logo,
                                adminName: $dto->adminName,
                                adminEmail: $dto->adminEmail,
                                adminPassword: $dto->adminPassword,
                                pagamento: $dto->pagamento,
                                afiliacao: new \App\Application\CadastroPublico\DTOs\AfiliacaoDTO(
                                    codigo: $afiliado->codigo,
                                    afiliadoId: $afiliado->id,
                                    descontoAplicado: null, // Será calculado
                                ),
                                idempotencyKey: $dto->idempotencyKey,
                                referenciaAfiliado: $dto->referenciaAfiliado,
                                sessionId: $dto->sessionId,
                            );
                        }
                    }
                }
            }

            // 1. Validar duplicidades (regra de negócio)
            // ⚡ REFATORADO: Agora usa ValidarDuplicidadesService com tabela global (O(1))
            $this->validarDuplicidadesService->validarEmail($dto->adminEmail);
            $this->validarDuplicidadesService->validarCnpj($dto->cnpj);

            // 2. Criar tenant com empresa e usuário admin
            $tenantResult = $this->criarTenantEUsuario($dto);

            // 3. Marcar referência como concluída (se houver)
            if ($referenciaAfiliado) {
                $this->rastrearReferenciaAfiliadoUseCase->marcarComoConcluida(
                    referenciaId: $referenciaAfiliado->id,
                    tenantId: $tenantResult['tenant']->id,
                    cnpj: $dto->cnpj
                );
            }

            // 4. Registrar afiliado na empresa (se aplicável)
            if ($dto->afiliacao) {
                $this->registrarAfiliado($tenantResult['empresa'], $dto->afiliacao);
                
                // Marcar cupom como aplicado na referência
                if ($referenciaAfiliado) {
                    $referenciaAfiliado->update(['cupom_aplicado' => true]);
                }
            }

            // 🔥 CORREÇÃO: Assinatura NÃO é criada automaticamente no cadastro
            // A assinatura só será criada quando o usuário escolher um plano internamente
            // através do fluxo de assinatura/pagamento

            // 5. Criar registro de onboarding (sempre com tutorial - sem assinatura inicial)
            $this->criarOnboarding(
                $tenantResult['tenant']->id, 
                $tenantResult['admin_user']->id, 
                $dto->adminEmail,
                concluirAutomaticamente: false // Sempre mostrar tutorial, sem assinatura
            );

            // 8. Disparar evento de empresa criada para enviar email de boas-vindas
            // 🔥 DDD: Evento de domínio disparado após criação bem-sucedida
            try {
                $this->eventDispatcher->dispatch(
                    new EmpresaCriada(
                        tenantId: $tenantResult['tenant']->id,
                        razaoSocial: $tenantResult['tenant']->razaoSocial,
                        cnpj: $tenantResult['tenant']->cnpj,
                        email: $dto->adminEmail, // Usar email do admin cadastrado
                        empresaId: $tenantResult['empresa']->id,
                        userId: $tenantResult['admin_user']->id ?? null, // Passar userId para evitar query extra no listener
                    )
                );
                
                Log::info('CadastrarEmpresaPublicamenteUseCase - Evento EmpresaCriada disparado', [
                    'tenant_id' => $tenantResult['tenant']->id,
                    'empresa_id' => $tenantResult['empresa']->id,
                    'email' => $dto->adminEmail,
                ]);
            } catch (\Exception $eventException) {
                // Não quebrar o fluxo se houver erro ao disparar evento
                Log::warning('CadastrarEmpresaPublicamenteUseCase - Erro ao disparar evento EmpresaCriada', [
                    'tenant_id' => $tenantResult['tenant']->id,
                    'error' => $eventException->getMessage(),
                ]);
            }

            $result = [
                'tenant' => $tenantResult['tenant'],
                'empresa' => $tenantResult['empresa'],
                'admin_user' => $tenantResult['admin_user'],
                'assinatura' => null, // 🔥 CORREÇÃO: Não criar assinatura automaticamente
                'plano' => null,
                'data_fim' => null,
                'payment_result' => null,
            ];

            // Salvar resultado no cache para idempotência (1 hora)
            if ($dto->idempotencyKey) {
                $cacheKey = "cadastro:idempotency:{$dto->idempotencyKey}";
                Cache::put($cacheKey, [
                    'result' => $result,
                    'cached_at' => now()->toIso8601String(),
                ], 3600); // 1 hora
            }

            $duration = (microtime(true) - $startTime) * 1000; // ms
            
            Log::info('CadastrarEmpresaPublicamenteUseCase::executar concluído', [
                'correlation_id' => $correlationId,
                'duration_ms' => round($duration, 2),
                'tenant_id' => $result['tenant']->id ?? null,
                'assinatura_id' => null, // 🔥 CORREÇÃO: Não criar assinatura no cadastro
                'success' => true,
            ]);

            return $result;
            
        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            Log::error('CadastrarEmpresaPublicamenteUseCase::executar falhou', [
                'correlation_id' => $correlationId,
                'duration_ms' => round($duration, 2),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'plano_id' => $dto->planoId,
                'admin_email' => $dto->adminEmail,
            ]);
            
            throw $e;
        }
    }

    /**
     * Cria tenant com empresa e usuário admin
     */
    private function criarTenantEUsuario(CadastroPublicoDTO $dto): array
    {
        // Normalizar CNPJ (remover formatação para garantir consistência)
        $cnpjNormalizado = $dto->cnpj ? preg_replace('/\D/', '', $dto->cnpj) : null;
        
        if (empty($cnpjNormalizado)) {
            throw new DomainException('CNPJ é obrigatório para cadastro de empresa.');
        }

        // Converter DTO para CriarTenantDTO (usar CNPJ normalizado)
        $tenantDTO = CriarTenantDTO::fromArray([
            'razao_social' => $dto->razaoSocial,
            'cnpj' => $cnpjNormalizado, // Salvar CNPJ sem formatação
            'email' => $dto->email,
            'endereco' => $dto->endereco,
            'cidade' => $dto->cidade,
            'estado' => $dto->estado,
            'cep' => $dto->cep,
            'telefones' => $dto->telefones,
            'logo' => $dto->logo,
            'status' => 'ativa',
            'admin_name' => $dto->adminName,
            'admin_email' => $dto->adminEmail,
            'admin_password' => $dto->adminPassword,
        ]);

        return $this->criarTenantUseCase->executar($tenantDTO, requireAdmin: true);
    }

    /**
     * Registra afiliado na empresa
     */
    private function registrarAfiliado($empresa, $afiliacao): void
    {
        try {
            $this->registrarAfiliadoNaEmpresaUseCase->executar(
                empresaId: $empresa->id,
                afiliadoId: $afiliacao->afiliadoId,
                codigo: $afiliacao->codigo,
                descontoAplicado: $afiliacao->descontoAplicado
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar afiliado na empresa durante cadastro público', [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id ?? null,
                'afiliado_id' => $afiliacao->afiliadoId ?? null,
            ]);
            // Não lança exceção - apenas loga para não bloquear o cadastro
        }
    }

    /**
     * Processa pagamento e cria assinatura
     */
    private function processarPagamentoECriarAssinatura(
        array $tenantResult,
        $plano,
        CadastroPublicoDTO $dto
    ): array {
        $isPlanoGratuito = $this->assinaturaDomainService->isPlanoGratuito($plano, $dto->periodo);

        // Se for plano gratuito, criar assinatura diretamente
        if ($isPlanoGratuito) {
            return $this->criarAssinaturaGratuita(
                $tenantResult['admin_user'],
                $tenantResult['tenant'],
                $tenantResult['empresa'],
                $plano,
                $dto
            );
        }

        // Se não houver dados de pagamento, criar assinatura pendente
        if (!$dto->pagamento) {
            return $this->criarAssinaturaPendente(
                $tenantResult['admin_user'],
                $tenantResult['tenant'],
                $tenantResult['empresa'],
                $plano,
                $dto
            );
        }

        // Processar pagamento
        return $this->processarPagamento(
            $tenantResult['admin_user'],
            $tenantResult['tenant'],
            $tenantResult['empresa'],
            $plano,
            $dto
        );
    }

    /**
     * Cria assinatura gratuita
     */
    private function criarAssinaturaGratuita($adminUser, $tenant, $empresa, $plano, CadastroPublicoDTO $dto): array
    {
        $dataInicio = Carbon::now();
        $dataFim = $this->assinaturaDomainService->calcularDataFim($plano, $dto->periodo, $dataInicio);
        $diasGracePeriod = $this->assinaturaDomainService->calcularDiasGracePeriod($plano);
        $metodoPagamento = $this->assinaturaDomainService->determinarMetodoPagamento($plano);

        $assinaturaDTO = new CriarAssinaturaDTO(
            userId: $adminUser->id,
            planoId: $plano->id,
            status: 'ativa',
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: 0.0,
            metodoPagamento: $metodoPagamento,
            transacaoId: null,
            diasGracePeriod: $diasGracePeriod,
            observacoes: 'Plano gratuito - teste de 3 dias',
            tenantId: $tenant->id,
            empresaId: $empresa->id, // 🔥 NOVO: Assinatura pertence à empresa
        );

        $assinatura = $this->criarAssinaturaUseCase->executar($assinaturaDTO);

        return [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];
    }

    /**
     * Cria assinatura pendente (sem pagamento processado)
     */
    private function criarAssinaturaPendente($adminUser, $tenant, $empresa, $plano, CadastroPublicoDTO $dto): array
    {
        $dataInicio = Carbon::now();
        $dataFim = $this->assinaturaDomainService->calcularDataFim($plano, $dto->periodo, $dataInicio);
        $valorOriginal = $this->assinaturaDomainService->calcularValor($plano, $dto->periodo);
        $diasGracePeriod = $this->assinaturaDomainService->calcularDiasGracePeriod($plano);

        // Aplicar desconto de afiliado se houver
        $valorPago = $valorOriginal;
        $observacoes = 'Cadastro público - pagamento pendente';

        if ($dto->afiliacao && $valorOriginal > 0) {
            try {
                $cupomInfo = $this->validarCupomAfiliadoUseCase->calcularDesconto(
                    $dto->afiliacao->codigo,
                    $valorOriginal
                );

                if ($cupomInfo['valido']) {
                    $valorPago = $cupomInfo['valor_final'];
                    $observacoes .= sprintf(
                        ' | Cupom %s aplicado: %s%% de desconto | Afiliado ID: %d',
                        $cupomInfo['codigo'],
                        $cupomInfo['percentual_desconto'],
                        $cupomInfo['afiliado_id']
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao aplicar cupom no cadastro público', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 🔥 CORREÇÃO: Status correto para planos pagos sem pagamento processado
        // Se é plano pago mas não há dados de pagamento, usar 'aguardando_pagamento'
        // Se for plano gratuito, usar 'ativa' (já validado em processarPagamentoECriarAssinatura)
        $status = 'aguardando_pagamento';
        $metodoPagamento = null;  // Ainda não foi pago
        
        $assinaturaDTO = new CriarAssinaturaDTO(
            userId: $adminUser->id,
            planoId: $plano->id,
            status: $status,
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: $valorPago,
            metodoPagamento: $metodoPagamento,
            transacaoId: null,
            diasGracePeriod: $diasGracePeriod,
            observacoes: $observacoes,
            tenantId: $tenant->id,
            empresaId: $empresa->id, // 🔥 NOVO: Assinatura pertence à empresa
        );

        $assinatura = $this->criarAssinaturaUseCase->executar($assinaturaDTO);

        return [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];
    }

    /**
     * Processa pagamento e cria assinatura
     */
    private function processarPagamento($adminUser, $tenant, $empresa, $plano, CadastroPublicoDTO $dto): array
    {
        $valorOriginal = $this->assinaturaDomainService->calcularValor($plano, $dto->periodo);
        
        // Se o plano for gratuito, não processar pagamento - criar assinatura gratuita diretamente
        if ($valorOriginal <= 0) {
            Log::info('CadastrarEmpresaPublicamenteUseCase::processarPagamento - Plano gratuito detectado, criando assinatura gratuita', [
                'plano_id' => $plano->id,
                'valor' => $valorOriginal,
            ]);
            
            return $this->criarAssinaturaGratuita(
                $adminUser,
                $tenant,
                $empresa,
                $plano,
                $dto
            );
        }
        
        // Aplicar desconto de afiliado se houver
        $valorFinal = $valorOriginal;
        if ($dto->afiliacao && $valorOriginal > 0) {
            try {
                $cupomInfo = $this->validarCupomAfiliadoUseCase->calcularDesconto(
                    $dto->afiliacao->codigo,
                    $valorOriginal
                );
                if ($cupomInfo['valido']) {
                    $valorFinal = $cupomInfo['valor_final'];
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao aplicar cupom no pagamento do cadastro público', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Garantir que o valor final ainda seja maior que zero após desconto
        if ($valorFinal <= 0) {
            Log::info('CadastrarEmpresaPublicamenteUseCase::processarPagamento - Valor final zero após desconto, criando assinatura gratuita', [
                'plano_id' => $plano->id,
                'valor_original' => $valorOriginal,
                'valor_final' => $valorFinal,
            ]);
            
            return $this->criarAssinaturaGratuita(
                $adminUser,
                $tenant,
                $empresa,
                $plano,
                $dto
            );
        }

        // 🔥 VALIDAÇÃO: Validar CPF se fornecido (obrigatório para PIX, opcional para cartão)
        $payerCpf = null;
        if ($dto->pagamento->payerCpf) {
            $cpfLimpo = preg_replace('/\D/', '', $dto->pagamento->payerCpf);
            
            // Validar formato básico
            if (strlen($cpfLimpo) !== 11) {
                throw new DomainException('CPF deve ter 11 dígitos.');
            }
            
            // Validar dígitos verificadores
            if (!$this->validarCpfComDigitosVerificadores($cpfLimpo)) {
                throw new DomainException('CPF inválido: dígitos verificadores incorretos.');
            }
            
            $payerCpf = $cpfLimpo; // Usar CPF limpo
        } elseif ($dto->pagamento->isPix()) {
            // PIX requer CPF obrigatoriamente
            throw new DomainException('CPF é obrigatório para pagamento via PIX.');
        }

        // Criar PaymentRequest
        $paymentRequestData = [
            'amount' => $valorFinal,
            'description' => "Plano {$plano->nome} - {$dto->periodo} - Sistema Rômulo",
            'payer_email' => $dto->pagamento->payerEmail,
            'payer_cpf' => $payerCpf,
            'payment_method_id' => $dto->pagamento->isPix() ? 'pix' : null,
            'external_reference' => "tenant_{$tenant->id}_plano_{$plano->id}_cadastro",
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
                'periodo' => $dto->periodo,
                'cadastro_publico' => true,
            ],
        ];

        // Para cartão, adicionar token e parcelas
        if ($dto->pagamento->isCreditCard()) {
            $paymentRequestData['card_token'] = $dto->pagamento->cardToken;
            $paymentRequestData['installments'] = $dto->pagamento->installments;
            unset($paymentRequestData['payment_method_id']);
        }

        $paymentRequest = PaymentRequest::fromArray($paymentRequestData);

        // Buscar modelo Eloquent do Tenant (ProcessarAssinaturaPlanoUseCase espera Eloquent model)
        $tenantModel = $this->tenantRepository->buscarModeloPorId($tenant->id);
        if (!$tenantModel) {
            throw new \DomainException('Tenant não encontrado após criação.');
        }

        // Processar pagamento usando o Use Case (passa modelo Eloquent)
        $assinatura = $this->processarAssinaturaPlanoUseCase->executar(
            $tenantModel,
            $plano,
            $paymentRequest,
            $dto->periodo
        );

        $dataFim = Carbon::parse($assinatura->data_fim);

        $result = [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];

        // Se assinatura está pendente, agendar verificação automática
        if (in_array($assinatura->status, ['suspensa', 'pendente']) && $assinatura->transacao_id) {
            // Agendar verificação em 5 minutos
            VerificarPagamentoPendenteJob::dispatch($assinatura->id)
                ->delay(now()->addMinutes(5))
                ->onQueue('payments');
            
            Log::info('VerificarPagamentoPendenteJob agendado', [
                'assinatura_id' => $assinatura->id,
                'status' => $assinatura->status,
                'transacao_id' => $assinatura->transacao_id,
            ]);
        }

        // Se for PIX pendente, incluir dados do QR Code
        // TODO: Refatorar ProcessarAssinaturaPlanoUseCase para retornar PaymentResultDTO
        // com dados do QR Code, evitando vazamento de infraestrutura (PaymentLog)
        if ($assinatura->status === 'pendente' && $assinatura->metodo_pagamento === 'pix') {
            // Buscar dados do pagamento via PaymentLog (infraestrutura)
            // Nota: Em DDD ideal, isso viria de um PaymentResultDTO retornado pelo
            // ProcessarAssinaturaPlanoUseCase, mas por enquanto mantemos compatibilidade
            // com a estrutura existente. Isso deveria ser abstraído via PaymentRepository.
            $paymentLog = \App\Models\PaymentLog::where('tenant_id', $tenantModel->id)
                ->where('plano_id', $plano->id)
                ->latest()
                ->first();
            
            if ($paymentLog && isset($paymentLog->dados_resposta['pix_qr_code'])) {
                $result['payment_result'] = [
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'pix_qr_code' => $paymentLog->dados_resposta['pix_qr_code'],
                    'pix_qr_code_base64' => $paymentLog->dados_resposta['pix_qr_code_base64'] ?? null,
                    'pix_ticket_url' => $paymentLog->dados_resposta['pix_ticket_url'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Cria registro de onboarding para o novo usuário
     * 
     * @param bool $concluirAutomaticamente Se true, conclui o onboarding automaticamente (para planos pagos)
     */
    private function criarOnboarding(int $tenantId, int $userId, string $email, bool $concluirAutomaticamente = false): void
    {
        try {
            // Criar DTO para iniciar onboarding
            $dtoIniciar = new IniciarOnboardingDTO(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: null,
                email: $email,
            );
            
            // Iniciar onboarding
            $onboarding = $this->gerenciarOnboardingUseCase->iniciar($dtoIniciar);

            // Se plano for pago, concluir onboarding automaticamente
            if ($concluirAutomaticamente) {
                $dtoConcluir = new ConcluirOnboardingDTO(
                    onboardingId: $onboarding->id,
                    tenantId: $tenantId,
                    userId: $userId,
                    sessionId: null,
                    email: $email,
                );
                
                $this->gerenciarOnboardingUseCase->concluir($dtoConcluir);
                
                Log::info('CadastrarEmpresaPublicamenteUseCase - Onboarding concluído automaticamente (plano pago)', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'email' => $email,
                    'onboarding_id' => $onboarding->id,
                ]);
            } else {
                Log::info('CadastrarEmpresaPublicamenteUseCase - Onboarding criado (plano gratuito - tutorial será mostrado)', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'email' => $email,
                    'onboarding_id' => $onboarding->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao criar onboarding durante cadastro público', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'email' => $email,
            ]);
            // Não lança exceção - apenas loga para não bloquear o cadastro
        }
    }

    /**
     * Verifica se um usuário está vinculado a pelo menos uma empresa ATIVA
     * 
     * Esta verificação é mais precisa que verificar se o tenant tem empresa ativa,
     * pois considera apenas as empresas às quais o usuário está efetivamente vinculado.
     * 
     * @param \App\Modules\Auth\Models\User $userModel
     * @return bool
     */
    /**
     * Verifica se o usuário está vinculado a uma empresa ativa e válida
     * 
     * IMPORTANTE: Este método NÃO verifica se o usuário está desativado (soft deleted),
     * pois essa verificação deve ser feita ANTES de chamar este método.
     * 
     * @param \App\Modules\Auth\Models\User $userModel
     * @return bool True se o usuário está vinculado a pelo menos uma empresa ativa e válida
     */
    private function verificarSeUsuarioTemEmpresaAtiva($userModel): bool
    {
        try {
            // 🔥 VALIDAÇÃO: Se o usuário está desativado (soft deleted), não tem empresa ativa
            // Esta verificação é redundante se já foi verificada antes, mas serve como segurança
            if ($userModel->trashed()) {
                Log::debug('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Usuário está desativado, não tem empresa ativa', [
                    'usuario_id' => $userModel->id,
                    'excluido_em' => $userModel->excluido_em?->toDateTimeString(),
                ]);
                return false;
            }
            
            // Carregar empresas do usuário (belongsToMany não suporta withTrashed() diretamente)
            // IMPORTANTE: empresas() retorna apenas empresas ativas (não deletadas)
            // Mas vamos verificar manualmente se há empresas deletadas na relação pivot
            $empresasIds = DB::table('empresa_user')
                ->where('user_id', $userModel->id)
                ->pluck('empresa_id')
                ->toArray();
            
            if (empty($empresasIds)) {
                Log::debug('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Usuário sem empresas vinculadas', [
                    'usuario_id' => $userModel->id,
                ]);
                return false;
            }
            
            // Buscar empresas incluindo deletadas (soft delete)
            // Usar modelo Eloquent diretamente para ter acesso a withTrashed()
            $empresas = \App\Models\Empresa::withTrashed()
                ->whereIn('id', $empresasIds)
                ->get();
            
            if ($empresas->isEmpty()) {
                Log::debug('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Nenhuma empresa encontrada nos IDs', [
                    'usuario_id' => $userModel->id,
                    'empresas_ids' => $empresasIds,
                ]);
                return false;
            }
            
            // Verificar se alguma empresa está ativa E tem razão social preenchida (não é empresa de teste)
            foreach ($empresas as $empresa) {
                // Verificar se empresa não está deletada (soft delete)
                if ($empresa->trashed()) {
                    Log::debug('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Empresa deletada, ignorando', [
                        'usuario_id' => $userModel->id,
                        'empresa_id' => $empresa->id,
                        'excluido_em' => $empresa->excluido_em?->toDateTimeString(),
                    ]);
                    continue;
                }
                
                $razaoSocial = $empresa->razao_social ?? '';
                $status = $empresa->status ?? 'inativa';
                $cnpj = $empresa->cnpj ?? '';
                
                // 🔥 VALIDAÇÃO ESTRITA: Empresa válida = ativa + tem razão social + tem CNPJ + não é empresa de teste + não está deletada
                // IMPORTANTE: Verificamos trashed() acima, mas vamos garantir novamente aqui por segurança
                $empresaAtiva = ($status === 'ativa') && !$empresa->trashed();
                $temRazaoSocial = !empty(trim($razaoSocial));
                $temCnpj = !empty(trim($cnpj));
                $naoEhEmpresaTeste = !$this->ehEmpresaDeTeste($razaoSocial);
                
                // 🔥 CRÍTICO: Só considerar empresa válida se TODAS as condições forem verdadeiras:
                // 1. Empresa está ativa (status = 'ativa' E não está deletada)
                // 2. Tem razão social preenchida
                // 3. Tem CNPJ OU não é empresa de teste
                if ($empresaAtiva && $temRazaoSocial && ($temCnpj || $naoEhEmpresaTeste)) {
                    Log::info('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Usuário vinculado a empresa ATIVA E VÁLIDA', [
                        'usuario_id' => $userModel->id,
                        'empresa_id' => $empresa->id,
                        'razao_social' => $razaoSocial,
                        'status' => $status,
                        'cnpj' => $cnpj,
                        'empresa_ativa' => true,
                        'empresa_deletada' => false,
                        'tem_razao_social' => true,
                        'tem_cnpj' => $temCnpj,
                        'nao_eh_empresa_teste' => $naoEhEmpresaTeste,
                    ]);
                    return true;
                } else {
                    Log::debug('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Empresa encontrada mas não é válida/ativa', [
                        'usuario_id' => $userModel->id,
                        'empresa_id' => $empresa->id,
                        'razao_social' => $razaoSocial,
                        'status' => $status,
                        'cnpj' => $cnpj,
                        'empresa_ativa' => $empresaAtiva,
                        'empresa_deletada' => $empresa->trashed(),
                        'tem_razao_social' => $temRazaoSocial,
                        'tem_cnpj' => $temCnpj,
                        'nao_eh_empresa_teste' => $naoEhEmpresaTeste,
                    ]);
                }
            }
            
            Log::debug('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Usuário sem empresa ativa válida', [
                'usuario_id' => $userModel->id,
                'total_empresas' => $empresas->count(),
                'usuario_desativado' => $userModel->trashed(),
                'empresas' => $empresas->map(fn($e) => [
                    'id' => $e->id,
                    'razao_social' => $e->razao_social,
                    'status' => $e->status ?? 'inativa',
                    'cnpj' => $e->cnpj ?? '',
                    'empresa_deletada' => $e->trashed(),
                    'excluido_em' => $e->excluido_em?->toDateTimeString(),
                ])->toArray(),
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            Log::warning('CadastrarEmpresaPublicamenteUseCase::verificarSeUsuarioTemEmpresaAtiva - Erro', [
                'usuario_id' => $userModel->id ?? null,
                'usuario_desativado' => $userModel->trashed() ?? false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            return false;
        }
    }
    
    /**
     * Valida CNPJ com dígitos verificadores
     * 
     * 🔥 VALIDAÇÃO: Usa a mesma lógica da Rule CnpjValido para garantir consistência
     * 
     * @param string $cnpj CNPJ sem formatação (14 dígitos)
     * @return bool True se CNPJ é válido
     */
    private function validarCnpjComDigitosVerificadores(string $cnpj): bool
    {
        // Verificar se todos os dígitos são iguais (CNPJs inválidos conhecidos)
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validar primeiro dígito verificador
        $soma = 0;
        $peso = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += intval($cnpj[$i]) * $peso;
            $peso = ($peso === 2) ? 9 : $peso - 1;
        }
        $digito1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito1 !== intval($cnpj[12])) {
            return false;
        }

        // Validar segundo dígito verificador
        $soma = 0;
        $peso = 6;
        for ($i = 0; $i < 13; $i++) {
            $soma += intval($cnpj[$i]) * $peso;
            $peso = ($peso === 2) ? 9 : $peso - 1;
        }
        $digito2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito2 !== intval($cnpj[13])) {
            return false;
        }

        return true;
    }

    /**
     * Valida CPF com dígitos verificadores
     * 
     * 🔥 VALIDAÇÃO: Usa a mesma lógica da Rule CpfValido para garantir consistência
     * 
     * @param string $cpf CPF sem formatação (11 dígitos)
     * @return bool True se CPF é válido
     */
    private function validarCpfComDigitosVerificadores(string $cpf): bool
    {
        // Verificar se todos os dígitos são iguais (CPFs inválidos conhecidos)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Validar primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $digito1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito1 !== intval($cpf[9])) {
            return false;
        }

        // Validar segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $digito2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito2 !== intval($cpf[10])) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se a razão social parece ser de uma empresa de teste/exemplo
     */
    private function ehEmpresaDeTeste(string $razaoSocial): bool
    {
        $razaoLower = strtolower(trim($razaoSocial));
        
        // Lista de padrões que indicam empresa de teste
        $padroesTeste = [
            'empresa exemplo',
            'empresa teste',
            'teste ltda',
            'exemplo ltda',
            'test company',
            'empresa de teste',
            'empresa de exemplo',
            'sample company',
            'demo company',
            'empresa demo',
        ];
        
        foreach ($padroesTeste as $padrao) {
            if (str_contains($razaoLower, $padrao)) {
                return true;
            }
        }
        
        return false;
    }
    
}

