<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
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
            
            // 2. Criar assinatura
            $assinaturaResult = $this->createAssinatura(
                $tenantResult['admin_user'],
                $tenantResult['tenant'],
                $validated
            );

            return $this->successResponse($tenantResult, $assinaturaResult);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
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
                    'Este CNPJ já está cadastrado no sistema. Se você é o responsável, faça login ou entre em contato com o suporte.',
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
     * Criar assinatura para o usuário
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
        
        // Calcular valor
        $valorPago = match (true) {
            $isPlanoGratuito => 0,
            $periodo === 'anual' && $plano->preco_anual => $plano->preco_anual,
            default => $plano->preco_mensal,
        };

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
            observacoes: $isPlanoGratuito 
                ? 'Plano gratuito - teste de 3 dias' 
                : 'Cadastro público - pagamento pendente',
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
    private function domainErrorResponse(DomainException $e): JsonResponse
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
