<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\CadastroPublico\UseCases\CadastrarEmpresaPublicamenteUseCase;
use App\Application\CadastroPublico\DTOs\CadastroPublicoDTO;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\EmailJaCadastradoException;
use App\Domain\Exceptions\EmailEmpresaDesativadaException;
use App\Domain\Exceptions\CnpjJaCadastradoException;
use App\Services\CnpjConsultaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * Controller para cadastro público (sem autenticação)
 * 
 * Permite criar tenant e usuário. Assinatura só será criada quando usuário escolher um plano internamente.
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
        private readonly CadastrarEmpresaPublicamenteUseCase $cadastrarEmpresaPublicamenteUseCase,
        private readonly CnpjConsultaService $cnpjConsultaService,
        private readonly \App\Application\CadastroPublico\Services\ValidarDuplicidadesService $validarDuplicidadesService,
    ) {}

    /**
     * Criar cadastro completo: tenant + assinatura + usuário
     * 
     * @OA\Post(
     *     path="/cadastro-publico",
     *     summary="Cadastro público de nova empresa",
     *     description="Cria tenant, empresa e usuário admin. Assinatura só será criada quando usuário escolher um plano internamente.",
     *     operationId="cadastroPublico",
     *     tags={"Cadastro Público"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"razao_social", "admin_name", "admin_email", "admin_password"},
     *             @OA\Property(property="plano_id", type="integer", example=1, description="Opcional - assinatura só será criada quando usuário escolher internamente"),
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

            // Converter array validado para DTO
            $dto = CadastroPublicoDTO::fromArray($validated);

            // Executar Use Case (toda a orquestração está aqui)
            $result = $this->cadastrarEmpresaPublicamenteUseCase->executar($dto);

            return $this->successResponse($result);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (EmailEmpresaDesativadaException $e) {
            // 🔥 SEGURANÇA: Retornar mensagem genérica para prevenir enumeração
            Log::warning('CadastroPublicoController::store - Email com empresa desativada', [
                'email' => $request->input('admin_email'),
                'exception' => get_class($e),
            ]);
            return $this->genericErrorResponse('Não foi possível completar o cadastro. Verifique seus dados ou entre em contato com o suporte.');
        } catch (EmailJaCadastradoException $e) {
            // 🔥 SEGURANÇA: Retornar mensagem genérica para prevenir enumeração
            Log::warning('CadastroPublicoController::store - Email já cadastrado', [
                'email' => $request->input('admin_email'),
                'exception' => get_class($e),
            ]);
            return $this->genericErrorResponse('Não foi possível completar o cadastro. Verifique seus dados ou entre em contato com o suporte.');
        } catch (CnpjJaCadastradoException $e) {
            // 🔥 SEGURANÇA: Retornar mensagem genérica para prevenir enumeração
            Log::warning('CadastroPublicoController::store - CNPJ já cadastrado', [
                'cnpj' => $request->input('cnpj'),
                'exception' => get_class($e),
            ]);
            return $this->genericErrorResponse('Não foi possível completar o cadastro. Verifique seus dados ou entre em contato com o suporte.');
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse($e);
        }
    }

    /**
     * Verificar disponibilidade de email (público)
     * 
     * @OA\Get(
     *     path="/cadastro-publico/verificar-email/{email}",
     *     summary="Verificar se email está disponível",
     *     description="Valida se email já está cadastrado (para validação em tempo real no frontend)",
     *     operationId="verificarEmailPublico",
     *     tags={"Cadastro Público"},
     *     @OA\Parameter(name="email", in="path", required=true, @OA\Schema(type="string", format="email")),
     *     @OA\Response(response=200, description="Email disponível ou não"),
     *     @OA\Response(response=422, description="Email inválido")
     * )
     */
    public function verificarEmail(Request $request): JsonResponse
    {
        $email = $request->input('email') ?? $request->route('email');
        
        if (!$email) {
            return response()->json([
                'message' => 'Email é obrigatório',
                'success' => false,
            ], 400);
        }
        
        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'available' => false,
                'message' => 'Email inválido',
                'success' => false,
            ], 422);
        }
        
        // ✅ SEGURANÇA: Sempre retornar mesma resposta para prevenir enumeração de emails
        // Aplicar delay artificial para prevenir timing attacks
        $startTime = microtime(true);
        $minDelay = 0.1; // 100ms delay mínimo
        
        try {
            // Usar o mesmo service de validação
            $this->validarDuplicidadesService->validarEmail($email);
        } catch (EmailEmpresaDesativadaException $e) {
            // Ignorar exceção - mesma resposta genérica
        } catch (EmailJaCadastradoException $e) {
            // Ignorar exceção - mesma resposta genérica
        } catch (\Exception $e) {
            // Logar outros erros mas não expor
            Log::error('Erro ao verificar email', [
                'email' => substr($email, 0, 3) . '***@***', // ✅ Não logar email completo
                'error' => $e->getMessage(),
            ]);
        }
        
        // ✅ Aplicar delay mínimo para prevenir timing attacks
        $elapsedTime = microtime(true) - $startTime;
        if ($elapsedTime < $minDelay) {
            usleep(($minDelay - $elapsedTime) * 1000000);
        }
        
        // ✅ Sempre retornar mesma resposta genérica (independente de existir ou não)
        return response()->json([
            'message' => 'Se este email estiver cadastrado, você receberá instruções.',
            'success' => true,
        ], 200);
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
            // Dados do plano (opcional - assinatura só será criada quando usuário escolher internamente)
            'plano_id' => 'nullable|integer|exists:planos,id',
            'periodo' => 'nullable|string|in:mensal,anual',
            
            // Dados da empresa (tenant)
            'razao_social' => 'required|string|max:255',
            'cnpj' => ['required', 'string', 'max:18', new \App\Rules\CnpjValido()],
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'whatsapp' => 'required|string|max:20',
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
            'payer_cpf' => ['nullable', 'string', new \App\Rules\CpfValido()], // Validar CPF se fornecido
            'card_token' => 'nullable|string|required_if:payment_method,credit_card',
            'installments' => 'nullable|integer|min:1|max:12',
            
            // Idempotência (opcional)
            'idempotency_key' => 'nullable|string|max:255',
            
            // Referência de afiliado (opcional - para rastreamento automático)
            'ref' => 'nullable|string|max:50',
            'referencia_afiliado' => 'nullable|string|max:50',
            'session_id' => 'nullable|string|max:255',
            
            // 🔥 MELHORIA: UTM Tracking (contexto de marketing)
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'fingerprint' => 'nullable|string|max:255',
        ]);
    }

    // ==================== RESPONSE HELPERS ====================

    /**
     * Resposta de sucesso
     */
    private function successResponse(array $result): JsonResponse
    {
        $tenant = $result['tenant'];
        $empresa = $result['empresa'] ?? null;
        $adminUser = $result['admin_user'] ?? null;
        $assinatura = $result['assinatura'] ?? null;
        $plano = $result['plano'] ?? null;
        $dataFim = $result['data_fim'] ?? null;

        $response = [
            'message' => 'Cadastro realizado com sucesso!',
            'success' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'razao_social' => $tenant->razaoSocial ?? $tenant->razao_social,
                    'cnpj' => $tenant->cnpj,
                    'email' => $tenant->email,
                ],
            ],
        ];

        // Incluir empresa se disponível
        if ($empresa) {
            $response['data']['empresa'] = [
                'id' => $empresa->id,
                'razao_social' => $empresa->razaoSocial ?? $empresa->razao_social,
            ];
        }

        // Incluir usuário se disponível
        if ($adminUser) {
            $response['data']['usuario'] = [
                'id' => $adminUser->id,
                'name' => $adminUser->nome ?? $adminUser->name,
                'email' => $adminUser->email,
            ];
        }

        // 🔥 MELHORIA: Incluir token JWT para auto-login (se disponível)
        if (isset($result['token']) && $result['token']) {
            $response['data']['token'] = $result['token'];
            $response['data']['token_type'] = 'Bearer';
        }

        // 🔥 MELHORIA: Incluir dados do onboarding no payload (Pre-fetching)
        if (isset($result['onboarding']) && $result['onboarding']) {
            $response['data']['onboarding'] = $result['onboarding'];
        }

        // 🔥 MELHORIA: Incluir assinatura trial se foi criada automaticamente
        if ($assinatura && $plano && $dataFim) {
            $response['data']['assinatura'] = [
                'id' => $assinatura->id,
                'status' => $assinatura->status ?? 'trial',
                'plano' => [
                    'id' => $plano->id,
                    'nome' => $plano->nome ?? 'Gratuito',
                ],
                'data_fim' => $dataFim instanceof \Carbon\Carbon ? $dataFim->format('Y-m-d') : $dataFim,
                'trial' => true, // Indica que é trial automático
            ];
        }

        // Incluir dados de pagamento se houver (ex: PIX QR Code)
        if (isset($result['payment_result'])) {
            $response['data']['payment'] = $result['payment_result'];
        }

        return response()->json($response, 201);
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
     * Resposta para email com empresa desativada
     */
    private function emailEmpresaDesativadaResponse(EmailEmpresaDesativadaException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'success' => false,
            'code' => 'EMAIL_EMPRESA_DESATIVADA',
            'email' => $e->getEmail(),
        ], 409);
    }

    /**
     * Resposta para email já cadastrado
     */
    private function emailExistsResponse(EmailJaCadastradoException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'success' => false,
            'code' => 'EMAIL_EXISTS',
            'redirect_to' => '/login',
            'email' => $e->getEmail(),
        ], 409);
    }

    /**
     * Resposta para CNPJ já cadastrado
     */
    private function cnpjExistsResponse(CnpjJaCadastradoException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'success' => false,
            'code' => 'CNPJ_EXISTS',
            'redirect_to' => '/login',
        ], 409);
    }
    
    /**
     * 🔥 SEGURANÇA: Resposta genérica para prevenir enumeração de emails
     */
    private function genericErrorResponse(string $message = 'Não foi possível completar o cadastro. Verifique seus dados ou entre em contato com o suporte.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'success' => false,
            'code' => 'CADASTRO_INDISPONIVEL',
        ], 400);
    }

    /**
     * Resposta de erro de domínio genérico
     */
    private function domainErrorResponse(DomainException $e): JsonResponse
    {
        $code = $e->getCode() ?: 400;
        $errorCode = $e->getErrorCode();
        
        $response = [
            'message' => $e->getMessage(),
            'success' => false,
        ];

        if ($errorCode) {
            $response['code'] = $errorCode;
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
