<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Application\Afiliado\UseCases\ValidarCupomAfiliadoUseCase;
use App\Application\Empresa\UseCases\RegistrarAfiliadoNaEmpresaUseCase;
use App\Application\Payment\UseCases\ProcessarAssinaturaPlanoUseCase;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Exceptions\DomainException;
use App\Services\CnpjConsultaService;
use App\Modules\Auth\Models\User;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Plano;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use OpenApi\Annotations as OA;

/**
 * Controller para cadastro público (sem autenticação)
 * 
 * Permite criar tenant, assinatura e usuário em uma única operação.
 * Segue padrão DDD com Use Cases e DTOs.
 * 
 * @OA\Tag(
 *     name="Cadastro Público",
 *     description="Endpoints públicos para cadastro de novos clientes"
 * )
 */
class CadastroPublicoController extends Controller
{
    public function __construct(
        private readonly CriarTenantUseCase $criarTenantUseCase,
        private readonly CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private readonly CnpjConsultaService $cnpjConsultaService,
        private readonly ValidarCupomAfiliadoUseCase $validarCupomAfiliadoUseCase,
        private readonly RegistrarAfiliadoNaEmpresaUseCase $registrarAfiliadoNaEmpresaUseCase,
        private readonly ProcessarAssinaturaPlanoUseCase $processarAssinaturaPlanoUseCase,
    ) {}

    /**
     * Criar cadastro completo: tenant + assinatura + usuário
     * 
     * @OA\Post(
     *     path="/cadastro-publico",
     *     summary="Cadastro público de nova empresa",
     *     description="Cria tenant, empresa, usuário admin e assinatura em uma única operação",
     *     operationId="cadastroPublico",
     *     tags={"Cadastro Público"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plano_id", "razao_social", "admin_name", "admin_email", "admin_password"},
     *             @OA\Property(property="plano_id", type="integer", example=1),
     *             @OA\Property(property="periodo", type="string", enum={"mensal", "anual"}, example="mensal"),
     *             @OA\Property(property="razao_social", type="string", example="Empresa LTDA"),
     *             @OA\Property(property="cnpj", type="string", example="12.345.678/0001-90"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="admin_name", type="string", example="João Silva"),
     *             @OA\Property(property="admin_email", type="string", format="email"),
     *             @OA\Property(property="admin_password", type="string", format="password", minLength=8)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Cadastro realizado com sucesso"),
     *     @OA\Response(response=409, description="Email ou CNPJ já cadastrado"),
     *     @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);

            // Verificar duplicidades antes de criar
            $this->checkDuplicates($validated);

            // 1. Criar tenant com empresa e usuário admin
            // Nota: Não usar DB::transaction() aqui pois a criação de tenant
            // envolve criar um novo banco de dados (operação DDL)
            $tenantResult = $this->createTenant($validated);
            
            // 2. Registrar afiliado na empresa se cupom foi usado
            if (!empty($validated['cupom_codigo']) && !empty($validated['afiliado_id'])) {
                $this->registrarAfiliadoNaEmpresa($tenantResult['empresa'], $validated);
            }
            
            // 3. Processar pagamento e criar assinatura
            $assinaturaResult = $this->processarPagamentoECriarAssinatura(
                $tenantResult['admin_user'],
                $tenantResult['tenant'],
                $validated
            );

            return $this->successResponse($tenantResult, $assinaturaResult);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e, $validated ?? null);
        } catch (\Exception $e) {
            return $this->serverErrorResponse($e);
        }
    }

    /**
     * Consultar CNPJ na Receita Federal (público)
     * 
     * @OA\Get(
     *     path="/cadastro-publico/consultar-cnpj/{cnpj}",
     *     summary="Consultar CNPJ na Receita Federal",
     *     description="Retorna dados da empresa para preenchimento automático",
     *     operationId="consultarCnpjPublico",
     *     tags={"Cadastro Público"},
     *     @OA\Parameter(name="cnpj", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Dados do CNPJ"),
     *     @OA\Response(response=404, description="CNPJ não encontrado")
     * )
     */
    public function consultarCnpj(Request $request): JsonResponse
    {
        $cnpj = $request->input('cnpj') ?? $request->route('cnpj');
        
        if (!$cnpj) {
            return response()->json([
                'message' => 'CNPJ é obrigatório',
                'success' => false,
            ], 400);
        }
        
        if (!$this->cnpjConsultaService->validarCnpj($cnpj)) {
            return response()->json([
                'message' => 'CNPJ inválido',
                'success' => false,
            ], 422);
        }
        
        $dados = $this->cnpjConsultaService->consultar($cnpj);
        
        if (!$dados) {
            return response()->json([
                'message' => 'CNPJ não encontrado ou serviço indisponível',
                'success' => false,
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $dados,
        ]);
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Validar dados da requisição
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            // Dados do plano
            'plano_id' => 'required|exists:planos,id',
            'periodo' => 'nullable|string|in:mensal,anual',
            
            // Dados da empresa (tenant)
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'logo' => 'nullable|string|max:500',
            
            // Dados do usuário administrador
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8',
            
            // Cupom de afiliado (opcional)
            'cupom_codigo' => 'nullable|string|max:50',
            'afiliado_id' => 'nullable|integer|exists:afiliados,id',
            'desconto_afiliado' => 'nullable|numeric|min:0|max:100',
            
            // Dados de pagamento (opcional - obrigatório se plano não for gratuito)
            'payment_method' => 'nullable|string|in:credit_card,pix',
            'payer_email' => 'nullable|email',
            'payer_cpf' => 'nullable|string',
            'card_token' => 'nullable|string|required_if:payment_method,credit_card',
            'installments' => 'nullable|integer|min:1|max:12',
        ]);
    }

    /**
     * Verificar se email ou CNPJ já existem
     * 
     * @throws DomainException
     */
    private function checkDuplicates(array $validated): void
    {
        // Verificar email
        if (User::where('email', $validated['admin_email'])->exists()) {
            Log::info('Tentativa de cadastro com email já existente', [
                'email' => $validated['admin_email'],
            ]);
            
            throw new DomainException(
                'Este e-mail já está cadastrado no sistema. Faça login para acessar sua conta.',
                409,
                null,
                'EMAIL_EXISTS'
            );
        }

        // Verificar CNPJ (se informado)
        if (!empty($validated['cnpj'])) {
            $cnpjLimpo = preg_replace('/\D/', '', $validated['cnpj']);
            
            $cnpjExiste = Tenant::where('cnpj', $validated['cnpj'])
                ->orWhere('cnpj', $cnpjLimpo)
                ->exists();
                
            if ($cnpjExiste) {
                Log::info('Tentativa de cadastro com CNPJ já existente', [
                    'cnpj' => $validated['cnpj'],
                ]);
                
                throw new DomainException(
                    'Este CNPJ já está cadastrado no sistema. Se você é o responsável, faça login para acessar sua conta.',
                    409,
                    null,
                    'CNPJ_EXISTS'
                );
            }
        }
    }

    /**
     * Criar tenant com empresa e usuário admin
     */
    private function createTenant(array $validated): array
    {
        $tenantDTO = CriarTenantDTO::fromArray($validated);
        return $this->criarTenantUseCase->executar($tenantDTO, requireAdmin: true);
    }

    /**
     * Registrar afiliado na empresa
     * 
     * Segue padrão DDD - usa Use Case ao invés de manipular entidade diretamente
     */
    private function registrarAfiliadoNaEmpresa($empresa, array $validated): void
    {
        try {
            // Usar Use Case para registrar afiliado (seguindo DDD)
            $this->registrarAfiliadoNaEmpresaUseCase->executar(
                empresaId: $empresa->id,
                afiliadoId: $validated['afiliado_id'],
                codigo: $validated['cupom_codigo'],
                descontoAplicado: $validated['desconto_afiliado'] ?? 0.0
            );

            // Registrar indicação na tabela central (afiliado_indicacoes)
            // Isso precisa ser feito no contexto central (não no tenant)
            // Por enquanto, apenas logamos - a indicação será registrada quando o pagamento for confirmado
            Log::info('Afiliado registrado na empresa durante cadastro público', [
                'empresa_id' => $empresa->id,
                'afiliado_id' => $validated['afiliado_id'],
                'cupom_codigo' => $validated['cupom_codigo'],
                'desconto' => $validated['desconto_afiliado'] ?? 0,
            ]);
        } catch (DomainException $e) {
            Log::error('Erro de domínio ao registrar afiliado na empresa', [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id ?? null,
                'afiliado_id' => $validated['afiliado_id'] ?? null,
            ]);
            // Não lança exceção - apenas loga o erro para não bloquear o cadastro
        } catch (\Exception $e) {
            Log::error('Erro ao registrar afiliado na empresa', [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id ?? null,
                'afiliado_id' => $validated['afiliado_id'] ?? null,
            ]);
            // Não lança exceção - apenas loga o erro para não bloquear o cadastro
        }
    }

    /**
     * Processar pagamento e criar assinatura
     * 
     * Se houver dados de pagamento e o plano não for gratuito, processa o pagamento.
     * Caso contrário, cria assinatura pendente ou gratuita.
     * 
     * @return array{assinatura: \App\Modules\Assinatura\Models\Assinatura, plano: \App\Modules\Assinatura\Models\Plano, data_fim: \Carbon\Carbon, payment_result?: array}
     */
    private function processarPagamentoECriarAssinatura($adminUser, $tenant, array $validated): array
    {
        $periodo = $validated['periodo'] ?? 'mensal';
        $plano = Plano::findOrFail($validated['plano_id']);
        
        $isPlanoGratuito = ($plano->preco_mensal == 0 || $plano->preco_mensal === null);
        
        // Se for plano gratuito, criar assinatura normalmente
        if ($isPlanoGratuito) {
            return $this->createAssinatura($adminUser, $tenant, $validated);
        }
        
        // Se não houver dados de pagamento, criar assinatura pendente
        if (empty($validated['payment_method']) || empty($validated['payer_email'])) {
            return $this->createAssinatura($adminUser, $tenant, $validated);
        }
        
        // Processar pagamento
        try {
            // Calcular valor com desconto de afiliado
            $valorOriginal = $periodo === 'anual' && $plano->preco_anual 
                ? $plano->preco_anual 
                : $plano->preco_mensal;
            
            $valorFinal = $valorOriginal;
            if (!empty($validated['cupom_codigo']) && !empty($validated['afiliado_id'])) {
                try {
                    $cupomInfo = $this->validarCupomAfiliadoUseCase->calcularDesconto(
                        $validated['cupom_codigo'],
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
            
            // Criar PaymentRequest
            $paymentRequestData = [
                'amount' => $valorFinal,
                'description' => "Plano {$plano->nome} - {$periodo} - Sistema Rômulo",
                'payer_email' => $validated['payer_email'],
                'payer_cpf' => $validated['payer_cpf'] ?? null,
                'payment_method_id' => $validated['payment_method'] === 'pix' ? 'pix' : null,
                'external_reference' => "tenant_{$tenant->id}_plano_{$plano->id}_cadastro",
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plano_id' => $plano->id,
                    'periodo' => $periodo,
                    'cadastro_publico' => true,
                ],
            ];
            
            // Para cartão, adicionar token e parcelas
            if ($validated['payment_method'] === 'credit_card') {
                $paymentRequestData['card_token'] = $validated['card_token'] ?? null;
                $paymentRequestData['installments'] = isset($validated['installments']) 
                    ? (int) $validated['installments'] 
                    : 1;
                unset($paymentRequestData['payment_method_id']);
            }
            
            $paymentRequest = PaymentRequest::fromArray($paymentRequestData);
            
            // Processar pagamento usando o Use Case
            $assinatura = $this->processarAssinaturaPlanoUseCase->executar(
                $tenant,
                $plano,
                $paymentRequest,
                $periodo
            );
            
            $dataFim = Carbon::parse($assinatura->data_fim);
            
            // Preparar resposta com dados do pagamento
            $result = [
                'assinatura' => $assinatura,
                'plano' => $plano,
                'data_fim' => $dataFim,
            ];
            
            // Se for PIX pendente, incluir dados do QR Code
            if ($assinatura->status === 'pendente' && $assinatura->metodo_pagamento === 'pix') {
                $paymentLog = \App\Models\PaymentLog::where('tenant_id', $tenant->id)
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
            
        } catch (DomainException $e) {
            Log::error('Erro ao processar pagamento no cadastro público', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
            ]);
            
            // Se falhar o pagamento, criar assinatura pendente
            return $this->createAssinatura($adminUser, $tenant, $validated);
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao processar pagamento no cadastro público', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
            ]);
            
            // Se falhar, criar assinatura pendente
            return $this->createAssinatura($adminUser, $tenant, $validated);
        }
    }

    /**
     * Criar assinatura para o usuário (método antigo - mantido para planos gratuitos)
     * 
     * @return array{assinatura: \App\Domain\Assinatura\Entities\Assinatura, plano: \App\Modules\Assinatura\Models\Plano, data_fim: \Carbon\Carbon}
     */
    private function createAssinatura($adminUser, $tenant, array $validated): array
    {
        $periodo = $validated['periodo'] ?? 'mensal';
        $plano = Plano::findOrFail($validated['plano_id']);
        
        $dataInicio = Carbon::now();
        $isPlanoGratuito = ($plano->preco_mensal == 0 || $plano->preco_mensal === null);
        
        // Calcular data de término
        $dataFim = match (true) {
            $isPlanoGratuito => $dataInicio->copy()->addDays(3),
            $periodo === 'anual' => $dataInicio->copy()->addYear(),
            default => $dataInicio->copy()->addMonth(),
        };
        
        // Calcular valor original
        $valorOriginal = match (true) {
            $isPlanoGratuito => 0,
            $periodo === 'anual' && $plano->preco_anual => $plano->preco_anual,
            default => $plano->preco_mensal,
        };

        // Aplicar desconto de afiliado se fornecido
        $valorPago = $valorOriginal;
        $observacoes = $isPlanoGratuito 
            ? 'Plano gratuito - teste de 3 dias' 
            : 'Cadastro público - pagamento pendente';

        if (!empty($validated['cupom_codigo']) && !empty($validated['afiliado_id']) && $valorOriginal > 0) {
            try {
                // Validar cupom novamente para garantir que ainda é válido
                $cupomInfo = $this->validarCupomAfiliadoUseCase->calcularDesconto(
                    $validated['cupom_codigo'],
                    $valorOriginal
                );

                if ($cupomInfo['valido'] && $cupomInfo['afiliado_id'] == $validated['afiliado_id']) {
                    $valorPago = $cupomInfo['valor_final'];
                    $desconto = $cupomInfo['valor_desconto'];
                    $observacoes .= sprintf(
                        ' | Cupom %s aplicado: %s%% de desconto (R$ %.2f) | Afiliado ID: %d',
                        $cupomInfo['codigo'],
                        $cupomInfo['percentual_desconto'],
                        $desconto,
                        $cupomInfo['afiliado_id']
                    );

                    // Registrar indicação do afiliado na empresa
                    // Isso será feito quando o tenant/empresa for criado
                    // Por enquanto, apenas logamos
                    Log::info('Cupom de afiliado aplicado no cadastro público', [
                        'afiliado_id' => $cupomInfo['afiliado_id'],
                        'tenant_id' => $tenant->id,
                        'cupom' => $cupomInfo['codigo'],
                        'desconto' => $desconto,
                        'valor_original' => $valorOriginal,
                        'valor_final' => $valorPago,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao aplicar cupom de afiliado no cadastro público', [
                    'error' => $e->getMessage(),
                    'cupom_codigo' => $validated['cupom_codigo'] ?? null,
                ]);
                // Continua sem desconto se houver erro na validação
            }
        }

        $assinaturaDTO = new CriarAssinaturaDTO(
            userId: $adminUser->id,
            planoId: $plano->id,
            status: 'ativa',
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: $valorPago,
            metodoPagamento: $isPlanoGratuito ? 'gratuito' : 'pendente',
            transacaoId: null,
            diasGracePeriod: $isPlanoGratuito ? 0 : 7,
            observacoes: $observacoes,
            tenantId: $tenant->id,
        );

        $assinatura = $this->criarAssinaturaUseCase->executar($assinaturaDTO);

        return [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];
    }

    // ==================== RESPONSE HELPERS ====================

    /**
     * Resposta de sucesso
     */
    private function successResponse(array $tenantResult, array $assinaturaResult): JsonResponse
    {
        $tenant = $tenantResult['tenant'];
        $empresa = $tenantResult['empresa'];
        $adminUser = $tenantResult['admin_user'];
        
        $assinatura = $assinaturaResult['assinatura'];
        $plano = $assinaturaResult['plano'];
        $dataFim = $assinaturaResult['data_fim'];

        return response()->json([
            'message' => 'Cadastro realizado com sucesso!',
            'success' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'razao_social' => $tenant->razaoSocial ?? $tenant->razao_social,
                    'cnpj' => $tenant->cnpj,
                    'email' => $tenant->email,
                ],
                'empresa' => [
                    'id' => $empresa->id,
                    'razao_social' => $empresa->razaoSocial ?? $empresa->razao_social,
                ],
                'usuario' => [
                    'id' => $adminUser->id,
                    'name' => $adminUser->nome ?? $adminUser->name,
                    'email' => $adminUser->email,
                ],
                'assinatura' => [
                    'id' => $assinatura->id,
                    'plano' => [
                        'id' => $plano->id,
                        'nome' => $plano->nome,
                    ],
                    'data_fim' => $dataFim->format('Y-m-d'),
                ],
            ],
        ], 201);
    }

    /**
     * Resposta de erro de validação
     */
    private function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'message' => 'Dados inválidos. Verifique os campos preenchidos.',
            'errors' => $e->errors(),
            'success' => false,
        ], 422);
    }

    /**
     * Resposta de erro de domínio
     */
    private function domainErrorResponse(DomainException $e, ?array $context = null): JsonResponse
    {
        $code = $e->getCode() ?: 400;
        $errorCode = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : null;
        
        $response = [
            'message' => $e->getMessage(),
            'success' => false,
        ];

        // Adicionar código específico se for duplicidade
        if ($errorCode) {
            $response['code'] = $errorCode;
            $response['redirect_to'] = '/login';
            
            // Incluir email na resposta se for EMAIL_EXISTS e tiver contexto
            if ($errorCode === 'EMAIL_EXISTS' && $context && isset($context['admin_email'])) {
                $response['email'] = $context['admin_email'];
            }
        }

        return response()->json($response, is_int($code) ? $code : 400);
    }

    /**
     * Resposta de erro de servidor
     */
    private function serverErrorResponse(\Exception $e): JsonResponse
    {
        Log::error('Erro ao realizar cadastro público', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Erro ao processar o cadastro. Por favor, tente novamente.',
            'error' => config('app.debug') ? $e->getMessage() : null,
            'success' => false,
        ], 500);
    }
}
