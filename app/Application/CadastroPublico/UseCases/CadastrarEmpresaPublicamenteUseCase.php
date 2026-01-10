<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\UseCases;

use App\Application\CadastroPublico\DTOs\CadastroPublicoDTO;
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
use App\Application\Onboarding\DTOs\ConcluirOnboardingDTO;
use App\Domain\Assinatura\Services\AssinaturaDomainService;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\EmailJaCadastradoException;
use App\Domain\Exceptions\CnpjJaCadastradoException;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Jobs\VerificarPagamentoPendenteJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly PlanoRepositoryInterface $planoRepository,
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
            $this->validarDuplicidades($dto);

            // 2. Buscar plano (via repository - não Eloquent direto)
            $plano = $this->planoRepository->buscarModeloPorId($dto->planoId);
            if (!$plano) {
                throw new \DomainException('Plano não encontrado.');
            }

            // 3. Criar tenant com empresa e usuário admin
            $tenantResult = $this->criarTenantEUsuario($dto);

            // 4. Marcar referência como concluída (se houver)
            if ($referenciaAfiliado) {
                $this->rastrearReferenciaAfiliadoUseCase->marcarComoConcluida(
                    referenciaId: $referenciaAfiliado->id,
                    tenantId: $tenantResult['tenant']->id,
                    cnpj: $dto->cnpj
                );
            }

            // 5. Registrar afiliado na empresa (se aplicável)
            if ($dto->afiliacao) {
                $this->registrarAfiliado($tenantResult['empresa'], $dto->afiliacao);
                
                // Marcar cupom como aplicado na referência
                if ($referenciaAfiliado) {
                    $referenciaAfiliado->update(['cupom_aplicado' => true]);
                }
            }

            // 6. Processar pagamento e criar assinatura
            $assinaturaResult = $this->processarPagamentoECriarAssinatura(
                $tenantResult,
                $plano,
                $dto
            );

            // 6.1. Criar indicação de afiliado (se aplicável e pagamento confirmado)
            if ($dto->afiliacao && $assinaturaResult['assinatura']->status === 'ativa') {
                $this->criarIndicacaoAfiliado(
                    $dto->afiliacao,
                    $tenantResult['tenant']->id,
                    $tenantResult['empresa']->id,
                    $plano,
                    $assinaturaResult
                );
            }

            // 7. Criar registro de onboarding
            // 🔥 IMPORTANTE: Se plano for PAGO, concluir onboarding automaticamente
            // Planos gratuitos devem passar pelo tutorial
            $isPlanoGratuito = !$plano->preco_mensal || $plano->preco_mensal == 0;
            $this->criarOnboarding(
                $tenantResult['tenant']->id, 
                $tenantResult['admin_user']->id, 
                $dto->adminEmail,
                concluirAutomaticamente: !$isPlanoGratuito // Concluir automaticamente se plano pago
            );

            $result = [
                'tenant' => $tenantResult['tenant'],
                'empresa' => $tenantResult['empresa'],
                'admin_user' => $tenantResult['admin_user'],
                'assinatura' => $assinaturaResult['assinatura'],
                'plano' => $assinaturaResult['plano'],
                'data_fim' => $assinaturaResult['data_fim'],
                'payment_result' => $assinaturaResult['payment_result'] ?? null,
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
                'assinatura_id' => $result['assinatura']->id ?? null,
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
     * Valida duplicidades de email e CNPJ
     * 
     * @throws EmailJaCadastradoException
     * @throws CnpjJaCadastradoException
     */
    private function validarDuplicidades(CadastroPublicoDTO $dto): void
    {
        // 🔥 VALIDAÇÃO DE EMAIL: Verificar em TODOS os tenants (multi-tenancy)
        // O email não pode estar cadastrado em nenhum tenant
        $emailEncontrado = false;
        
        // 1. Verificar no banco central (admin users) se necessário
        // Nota: Admin users geralmente têm um repositório separado, mas vamos verificar nos tenants primeiro
        
        // 2. Verificar em todos os tenants
        try {
            Log::info('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Verificando email em todos os tenants', [
                'email' => $dto->adminEmail,
            ]);
            
            // Buscar todos os tenants
            $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
            $tenants = $tenantsPaginator->getCollection();
            
            Log::debug('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Total de tenants para verificar', [
                'total_tenants' => $tenants->count(),
                'email' => $dto->adminEmail,
            ]);
            
            foreach ($tenants as $tenantDomain) {
                try {
                    // Buscar modelo Eloquent para inicializar tenancy
                    $tenant = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    if (!$tenant) {
                        continue;
                    }
                    
                    tenancy()->initialize($tenant);
                    
                    // Verificar se o email existe neste tenant
                    $userDomain = $this->userRepository->buscarPorEmail($dto->adminEmail);
                    
                    if ($userDomain) {
                        $emailEncontrado = true;
                        Log::warning('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Email já cadastrado em tenant', [
                            'email' => $dto->adminEmail,
                            'tenant_id' => $tenant->id,
                            'tenant_razao_social' => $tenantDomain->razaoSocial,
                        ]);
                        tenancy()->end();
                        break;
                    }
                    
                    tenancy()->end();
                } catch (\Exception $e) {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                    Log::warning('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Erro ao verificar email no tenant', [
                        'tenant_id' => $tenantDomain->id,
                        'email' => $dto->adminEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            if ($emailEncontrado) {
                Log::info('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Email já existe, bloqueando cadastro', [
                    'email' => $dto->adminEmail,
                ]);
                throw new EmailJaCadastradoException($dto->adminEmail);
            }
            
            Log::info('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Email não encontrado em nenhum tenant, permitindo cadastro', [
                'email' => $dto->adminEmail,
            ]);
            
        } catch (EmailJaCadastradoException $e) {
            // Re-lançar a exceção se já foi lançada
            throw $e;
        } catch (\Exception $e) {
            Log::error('CadastrarEmpresaPublicamenteUseCase::validarDuplicidades - Erro ao validar email', [
                'email' => $dto->adminEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Em caso de erro na validação, bloquear o cadastro por segurança
            throw new EmailJaCadastradoException($dto->adminEmail);
        }

        // Validar CNPJ (obrigatório agora)
        if (empty($dto->cnpj)) {
            throw new DomainException('CNPJ é obrigatório para cadastro de empresa.');
        }

        // Normalizar CNPJ (remover formatação para busca e comparação)
        $cnpjLimpo = preg_replace('/\D/', '', $dto->cnpj);
        
        // Validar formato básico
        if (strlen($cnpjLimpo) !== 14) {
            throw new DomainException('CNPJ deve ter 14 dígitos.');
        }

        // Verificar se CNPJ já existe (buscar tanto formatado quanto limpo)
        $tenantExistente = $this->tenantRepository->buscarPorCnpj($dto->cnpj);
        
        if (!$tenantExistente) {
            $tenantExistente = $this->tenantRepository->buscarPorCnpj($cnpjLimpo);
        }
        
        if ($tenantExistente) {
            Log::info('Tentativa de cadastro com CNPJ já existente', [
                'cnpj' => $dto->cnpj,
                'tenant_id_existente' => $tenantExistente->id,
            ]);
            
            throw new CnpjJaCadastradoException($dto->cnpj);
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

        $assinaturaDTO = new CriarAssinaturaDTO(
            userId: $adminUser->id,
            planoId: $plano->id,
            status: 'pendente',
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: $valorPago,
            metodoPagamento: 'pendente',
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

        // Criar PaymentRequest
        $paymentRequestData = [
            'amount' => $valorFinal,
            'description' => "Plano {$plano->nome} - {$dto->periodo} - Sistema Rômulo",
            'payer_email' => $dto->pagamento->payerEmail,
            'payer_cpf' => $dto->pagamento->payerCpf,
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
            // Iniciar onboarding
            $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: null,
                email: $email,
            );

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
}

