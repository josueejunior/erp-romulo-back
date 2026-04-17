<?php

namespace App\Modules\Assinatura\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Assinatura\UseCases\BuscarAssinaturaAtualUseCase;
use App\Application\Assinatura\UseCases\ObterStatusAssinaturaUseCase;
use App\Application\Assinatura\UseCases\ListarAssinaturasUseCase;
use App\Application\Assinatura\UseCases\CancelarAssinaturaUseCase;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\UseCases\TrocarPlanoAssinaturaUseCase;
use App\Application\Assinatura\UseCases\BuscarTenantDoUsuarioUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Application\Assinatura\Resources\AssinaturaResource;
use App\Application\Payment\UseCases\RenovarAssinaturaUseCase;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Http\Requests\Assinatura\RenovarAssinaturaRequest;
use App\Http\Requests\Assinatura\CriarAssinaturaRequest;
use App\Http\Requests\Assinatura\TrocarPlanoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para Assinaturas
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Usa Resources para transformação
 * - Não acessa modelos Eloquent diretamente
 * - Não contém lógica de infraestrutura (cache, etc.)
 * 
 * Segue o mesmo padrão do OrgaoController:
 * - Tenant ID: Obtido automaticamente via tenancy()->tenant (middleware já inicializou)
 * - Empresa ID: Obtido automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID
 */
class AssinaturaController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private BuscarAssinaturaAtualUseCase $buscarAssinaturaAtualUseCase,
        private ObterStatusAssinaturaUseCase $obterStatusAssinaturaUseCase,
        private ListarAssinaturasUseCase $listarAssinaturasUseCase,
        private CancelarAssinaturaUseCase $cancelarAssinaturaUseCase,
        private CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private TrocarPlanoAssinaturaUseCase $trocarPlanoAssinaturaUseCase,
        private RenovarAssinaturaUseCase $renovarAssinaturaUseCase,
        private BuscarTenantDoUsuarioUseCase $buscarTenantDoUsuarioUseCase,
        private PaymentProviderInterface $paymentProvider,
        private AssinaturaResource $assinaturaResource,
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}



    /**
     * Retorna assinatura atual do USUÁRIO
     * Retorna entidade de domínio transformada via Resource
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Obtém usuário autenticado
     * - Busca tenant baseado na empresa ativa do USUÁRIO
     * - Chama Use Case para buscar assinatura
     * - Transforma entidade em DTO de resposta
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não usa tenant do contexto sem validar
     * - A validação é baseada no USUÁRIO, não no tenant/empresa do header
     * 
     * 🔥 IMPORTANTE: A assinatura é validada pelo USUÁRIO, não pelo tenant/empresa.
     * Busca o tenant onde a empresa ativa do usuário está.
     * Permite acesso mesmo sem assinatura (retorna null) para que o frontend possa tratar.
     */
    public function atual(Request $request): JsonResponse
    {
        try {
            // Obter usuário autenticado (fonte de verdade)
            $user = $this->getUserOrFail();
            
            // Buscar tenant baseado na empresa ativa do USUÁRIO
            $tenant = $this->buscarTenantDoUsuarioUseCase->executar($user);
            
            if (!$tenant || !$user->empresa_ativa_id) {
                return response()->json([
                    'data' => null,
                    'message' => 'Nenhuma assinatura ou empresa ativa encontrada',
                    'code' => 'NO_SUBSCRIPTION'
                ], 200);
            }

            // 🔥 CORRIGIDO: Buscar assinatura da EMPRESA ATIVA do usuário
            try {
                $assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($user->empresa_ativa_id, $tenant->id);
                
                if (!$assinatura) {
                    return response()->json([
                        'data' => null,
                        'message' => 'Nenhuma assinatura encontrada para esta empresa',
                        'code' => 'NO_SUBSCRIPTION'
                    ], 200);
                }
                
                // 🔥 NOVO: Obter também o status de uso para uma resposta completa (MAIS EFICIENTE)
                $usage = null;
                $warning = null;
                try {
                    $statusData = $this->obterStatusAssinaturaUseCase->executar(
                        $user->empresa_ativa_id, 
                        $user->empresa_ativa_id,
                        $tenant->id
                    );
                    $usage = [
                        'processos_utilizados' => $statusData['processos_utilizados'] ?? 0,
                        'usuarios_utilizados' => $statusData['usuarios_utilizados'] ?? 0,
                        'limite_processos' => $statusData['limite_processos'],
                        'limite_usuarios' => $statusData['limite_usuarios'],
                    ];
                    $warning = $statusData['warning'] ?? null;
                } catch (\Exception $e) {
                    \Log::warning('Erro ao carregar status de uso na assinatura: ' . $e->getMessage());
                }

                // Transformar entidade do domínio em DTO de resposta rico
                $responseDTO = $this->assinaturaResource->toResponse($assinatura, $usage, $warning);

                return response()->json([
                    'data' => $responseDTO->toArray()
                ], 200);

            } catch (\App\Domain\Exceptions\NotFoundException $e) {
                return response()->json([
                    'data' => null,
                    'message' => 'Nenhuma assinatura encontrada',
                    'code' => 'NO_SUBSCRIPTION'
                ], 200);
            }
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar assinatura atual');
        }
    }
    

    /**
     * Retorna status da assinatura com limites utilizados
     * Retorna dados de status e limites utilizados
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Obtém usuário autenticado
     * - Busca tenant baseado na empresa ativa do USUÁRIO
     * - Obtém empresa automaticamente via getEmpresaAtivaOrFail()
     * - Chama Use Case para obter status
     * - Retorna dados de status e limites
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não usa tenant do contexto sem validar
     * - A validação é baseada no USUÁRIO, não no tenant/empresa do header
     * 
     * 🔥 IMPORTANTE: A assinatura é validada pelo USUÁRIO, não pelo tenant/empresa.
     * Permite acesso mesmo sem assinatura (retorna null) para que o frontend possa tratar.
     */
    public function status(Request $request): JsonResponse
    {
        try {
            // Obter usuário autenticado (fonte de verdade)
            $user = $this->getUserOrFail();
            
            // Buscar tenant baseado na empresa ativa do USUÁRIO
            $tenant = $this->buscarTenantDoUsuarioUseCase->executar($user);
            
            if (!$tenant) {
                \Log::warning('AssinaturaController::status() - Não foi possível determinar tenant do usuário', [
                    'user_id' => $user->id,
                    'empresa_ativa_id' => $user->empresa_ativa_id,
                ]);
                
                return response()->json([
                    'data' => [
                        'status' => null,
                        'limite_processos' => null,
                        'limite_usuarios' => null,
                        'limite_armazenamento_mb' => null,
                        'processos_utilizados' => 0,
                        'usuarios_utilizados' => 0,
                        'mensagem' => 'Nenhuma assinatura encontrada',
                        'code' => 'NO_SUBSCRIPTION'
                    ]
                ], 200);
            }
            
            // 🔥 CRÍTICO: Verificar se usuário tem empresa ativa
            if (!$user->empresa_ativa_id) {
                \Log::warning('AssinaturaController::status() - Usuário não tem empresa ativa', [
                    'user_id' => $user->id,
                ]);
                
                return response()->json([
                    'data' => [
                        'status' => null,
                        'limite_processos' => null,
                        'limite_usuarios' => null,
                        'limite_armazenamento_mb' => null,
                        'processos_utilizados' => 0,
                        'usuarios_utilizados' => 0,
                        'mensagem' => 'Nenhuma empresa ativa encontrada. Selecione uma empresa para ver a assinatura.',
                        'code' => 'NO_ACTIVE_COMPANY'
                    ]
                ], 200);
            }

            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // 🔥 CORRIGIDO: Buscar status da assinatura da EMPRESA ATIVA, não do usuário
            // A assinatura pertence à empresa, não ao usuário
            try {
                $statusData = $this->obterStatusAssinaturaUseCase->executar(
                    $user->empresa_ativa_id, 
                    $empresa->id,
                    $tenant->id
                );

                return response()->json([
                    'data' => $statusData
                ]);
            } catch (\App\Domain\Exceptions\NotFoundException $e) {
                // Não há assinatura - retornar dados vazios para que o frontend possa tratar
                return response()->json([
                    'data' => [
                        'status' => null,
                        'limite_processos' => null,
                        'limite_usuarios' => null,
                        'limite_armazenamento_mb' => null,
                        'processos_utilizados' => 0,
                        'usuarios_utilizados' => 0,
                        'mensagem' => 'Nenhuma assinatura encontrada',
                        'code' => 'NO_SUBSCRIPTION'
                    ]
                ], 200);
            }
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao obter status da assinatura');
        }
    }

    /**
     * Lista assinaturas do tenant
     * Retorna entidades de domínio transformadas
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Obtém tenant automaticamente via getTenantOrFail()
     * - Aplica filtros opcionais
     * - Chama Use Case para listar
     * - Retorna collection de arrays
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Obter tenant automaticamente (middleware já inicializou baseado no X-Tenant-ID)
            $tenant = $this->getTenantOrFail();

            // Preparar filtros
            $filtros = [];
            if ($request->has('status')) {
                $filtros['status'] = $request->status;
            }

            // Executar Use Case (retorna Collection de arrays)
            $assinaturas = $this->listarAssinaturasUseCase->executar($tenant->id, $filtros);

            return response()->json([
                'data' => $assinaturas->values()->all(),
                'meta' => [
                    'total' => $assinaturas->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar assinaturas');
        }
    }

    /**
     * Cria nova assinatura manualmente (admin ou sistema)
     * Retorna entidade de domínio transformada via Resource
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request)
     * - Obtém tenant automaticamente
     * - Chama Use Case para criar
     * - Transforma entidade em DTO de resposta
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * Nota: Assinaturas normalmente são criadas via PaymentController::processarAssinatura()
     * Este método é para casos especiais (ex: admin criar assinatura gratuita)
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     */
    public function store(CriarAssinaturaRequest $request): JsonResponse
    {
        try {
            // Obter tenant automaticamente (middleware já inicializou baseado no X-Tenant-ID)
            $tenant = $this->getTenantOrFail();

            // Criar DTO a partir do request validado
            $dto = CriarAssinaturaDTO::fromArray([
                ...$request->validated(),
                'tenant_id' => $tenant->id,
            ]);

            // Executar Use Case (contém toda a lógica de negócio)
            $assinatura = $this->criarAssinaturaUseCase->executar($dto);

            // Transformar entidade em DTO de resposta
            $responseDTO = $this->assinaturaResource->toResponse($assinatura);

            return response()->json([
                'message' => 'Assinatura criada com sucesso',
                'data' => $responseDTO->toArray(),
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar assinatura');
        }
    }

    /**
     * Renova assinatura
     * Retorna entidade de domínio transformada via Resource
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request)
     * - Obtém tenant automaticamente
     * - Valida que assinatura pertence ao tenant
     * - Chama Use Case para renovar
     * - Transforma entidade em DTO de resposta
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     */
    public function renovar(RenovarAssinaturaRequest $request, $assinatura): JsonResponse
    {
        try {
            // Obter tenant automaticamente (middleware já inicializou baseado no X-Tenant-ID)
            $tenant = $this->getTenantOrFail();

            // Buscar assinatura usando repository (DDD)
            $assinaturaDomain = $this->assinaturaRepository->buscarPorId($assinatura);
            if (!$assinaturaDomain) {
                return response()->json(['message' => 'Assinatura não encontrada'], 404);
            }

            // Validar que a assinatura pertence ao tenant
            if ($assinaturaDomain->tenantId !== $tenant->id) {
                return response()->json(['message' => 'Assinatura não encontrada'], 404);
            }

            // Buscar modelo para acessar relacionamento com plano (necessário para renovação)
            $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinatura);
            if (!$assinaturaModel || !$assinaturaModel->plano) {
                return response()->json(['message' => 'Plano da assinatura não encontrado'], 404);
            }

            // Request já está validado via Form Request
            $validated = $request->validated();

            // Dados base
            $meses = $validated['meses'];
            $plano = $assinaturaModel->plano;

            // Calcular valor base (lógica centralizada no Model)
            $periodo = $meses === 12 ? 'anual' : 'mensal';
            $valor = $plano->calcularPreco($periodo, $meses);

            // Buscar dados da empresa para criar referência do pedido
            $empresaFinder = new \App\Domain\Tenant\Services\EmpresaFinder();
            $empresaData = $empresaFinder->findPrincipalByTenantId($tenant->id);
            $nomeEmpresa = $empresaData['razao_social'] ?? $tenant->razao_social ?? 'Empresa';
            $cnpjEmpresa = $empresaData['cnpj'] ?? $tenant->cnpj ?? '';
            
            // Criar referência do pedido: Nome da empresa_plano_cnpj
            $rawReference = $nomeEmpresa . '_' . $plano->nome . '_' . ($cnpjEmpresa ?: 'sem_cnpj');
            // Sanitizar
            $safeReference = preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace(' ', '_', $rawReference));
            $externalReference = substr($safeReference, 0, 256);
            
            // Determinar método de pagamento
            $paymentMethod = $validated['payment_method_id'] ?? 'credit_card';
            
            // Criar PaymentRequest
            $paymentRequestData = [
                'amount' => $valor,
                'description' => "Renovação de assinatura - Plano {$plano->nome} - {$meses} " . ($meses === 1 ? 'mês' : 'meses'),
                'payer_email' => $validated['payer_email'],
                'payer_cpf' => $validated['payer_cpf'] ?? null,
                'payment_method_id' => $paymentMethod === 'pix' ? 'pix' : null,
                'external_reference' => $externalReference,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'assinatura_id' => $assinaturaModel->id,
                    'plano_id' => $plano->id,
                    'meses' => $meses,
                ],
            ];

            if ($paymentMethod === 'credit_card') {
                $paymentRequestData['card_token'] = $validated['card_token'];
                $paymentRequestData['installments'] = $validated['installments'] ?? 1;
                unset($paymentRequestData['payment_method_id']);
            }

            $paymentRequest = \App\Domain\Payment\ValueObjects\PaymentRequest::fromArray($paymentRequestData);

            // Processar renovação usando Use Case injetado
            $assinaturaRenovada = $this->renovarAssinaturaUseCase->executar(
                $assinaturaModel,
                $paymentRequest,
                $meses
            );

            // Buscar log de pagamento para PIX
            $paymentLog = \App\Models\PaymentLog::where('external_id', $assinaturaRenovada->transacao_id)->first();
            
            // Buscar entidade renovada e transformar em DTO
            $assinaturaRenovadaDomain = $this->assinaturaRepository->buscarPorId($assinaturaRenovada->id);
            $responseData = [];

            if ($assinaturaRenovadaDomain) {
                $responseDTO = $this->assinaturaResource->toResponse($assinaturaRenovadaDomain);
                $responseData = $responseDTO->toArray();
            } else {
                $responseData = [
                    'id' => $assinaturaRenovada->id,
                    'status' => $assinaturaRenovada->status,
                    'data_fim' => $assinaturaRenovada->data_fim->format('Y-m-d'),
                    'dias_restantes' => $assinaturaRenovada->diasRestantes(),
                ];
            }

            // Se for PIX e estiver pendente, incluir dados do QR Code
            if ($paymentMethod === 'pix' && $assinaturaRenovada->status !== 'ativa' && $paymentLog) {
                $dadosResposta = $paymentLog->dados_resposta ?? [];
                if (isset($dadosResposta['pix_qr_code_base64']) || isset($dadosResposta['pix_qr_code'])) {
                    $responseData['pix_qr_code_base64'] = $dadosResposta['pix_qr_code_base64'] ?? null;
                    $responseData['pix_qr_code'] = $dadosResposta['pix_qr_code'] ?? null;
                    $responseData['pix_ticket_url'] = $dadosResposta['pix_ticket_url'] ?? null;
                    $responseData['payment_id'] = $paymentLog->external_id ?? null;
                    $responseData['amount'] = (float) $paymentLog->valor;
                }
            }

            return response()->json([
                'message' => 'Assinatura renovada com sucesso',
                'data' => $responseData,
                'pending' => $assinaturaRenovada->status !== 'ativa',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\App\Domain\Exceptions\DomainException | \App\Domain\Exceptions\BusinessRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao renovar assinatura');
        }
    }

    /**
     * Cancela assinatura
     * Retorna entidade de domínio transformada via Resource
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Obtém tenant automaticamente
     * - Chama Use Case para cancelar
     * - Transforma entidade em DTO de resposta
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     */
    public function cancelar(Request $request, $assinatura): JsonResponse
    {
        try {
            // Obter tenant automaticamente (middleware já inicializou baseado no X-Tenant-ID)
            $tenant = $this->getTenantOrFail();

            // Executar Use Case (retorna modelo, mas vamos buscar entidade para transformar)
            $assinaturaCanceladaModel = $this->cancelarAssinaturaUseCase->executar($tenant->id, $assinatura);

            // Buscar entidade cancelada e transformar em DTO
            $assinaturaCanceladaDomain = $this->assinaturaRepository->buscarPorId($assinaturaCanceladaModel->id);
            if ($assinaturaCanceladaDomain) {
                $responseDTO = $this->assinaturaResource->toResponse($assinaturaCanceladaDomain);
                
                return response()->json([
                    'message' => 'Assinatura cancelada com sucesso',
                    'data' => $responseDTO->toArray(),
                ], 200);
            }

            // Fallback: retornar dados do modelo
            return response()->json([
                'message' => 'Assinatura cancelada com sucesso',
                'data' => [
                    'id' => $assinaturaCanceladaModel->id,
                    'status' => $assinaturaCanceladaModel->status,
                    'data_cancelamento' => $assinaturaCanceladaModel->data_cancelamento?->format('Y-m-d H:i:s'),
                ],
            ], 200);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao cancelar assinatura');
        }
    }

    /**
     * Trocar plano da assinatura (upgrade/downgrade)
     * Retorna entidade de domínio transformada via Resource
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request)
     * - Obtém tenant automaticamente
     * - Chama Use Case para trocar plano (calcula pro-rata)
     * - Processa pagamento se necessário
     * - Transforma entidade em DTO de resposta
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * Calcula pro-rata e permite trocar de plano mantendo crédito proporcional.
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     */
    public function trocarPlano(TrocarPlanoRequest $request): JsonResponse
    {
        try {
            // Obter tenant automaticamente (middleware já inicializou baseado no X-Tenant-ID)
            $tenant = $this->getTenantOrFail();

            // Request já está validado
            $validated = $request->validated();
            $novoPlanoId = $validated['plano_id'];
            $periodo = $validated['periodo'];

            // Executar Use Case para trocar plano (calcula pro-rata)
            $resultado = $this->trocarPlanoAssinaturaUseCase->executar($tenant->id, $novoPlanoId, $periodo);

            $novaAssinatura = $resultado['assinatura'];
            $credito = $resultado['credito'];
            $valorCobrar = $resultado['valor_cobrar'];

            // Se há valor a cobrar, processar pagamento
            if ($valorCobrar > 0 && isset($validated['payment_data'])) {
                $paymentData = $validated['payment_data'];
                
                // Buscar plano da nova assinatura
                $novaAssinaturaModel = $this->assinaturaRepository->buscarModeloPorId($novaAssinatura->id);
                $novoPlano = $novaAssinaturaModel->plano;
                
                // Buscar dados da empresa para criar referência do pedido
                $empresaFinder = new \App\Domain\Tenant\Services\EmpresaFinder();
                $empresaData = $empresaFinder->findPrincipalByTenantId($tenant->id);
                $nomeEmpresa = $empresaData['razao_social'] ?? $tenant->razao_social ?? 'Empresa';
                $cnpjEmpresa = $empresaData['cnpj'] ?? $tenant->cnpj ?? '';
                
                // Criar referência do pedido: Nome da empresa_plano_cnpj
                $rawReference = $nomeEmpresa . '_' . $novoPlano->nome . '_' . ($cnpjEmpresa ?: 'sem_cnpj');
                // Sanitizar
                $safeReference = preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace(' ', '_', $rawReference));
                $externalReference = substr($safeReference, 0, 256);
                
                // Determinar método de pagamento
                $paymentMethod = $paymentData['payment_method_id'] ?? 'credit_card';

                $paymentRequestData = [
                    'amount' => $valorCobrar,
                    'description' => "Troca de plano - Crédito aplicado: R$ {$credito}",
                    'payer_email' => $paymentData['payer_email'],
                    'payer_cpf' => $paymentData['payer_cpf'] ?? null,
                    'payment_method_id' => $paymentMethod === 'pix' ? 'pix' : null,
                    'external_reference' => $externalReference,
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $novaAssinatura->id,
                        'plano_id' => $novoPlanoId,
                        'credito_aplicado' => $credito,
                        'tipo' => 'troca_plano',
                    ],
                ];

                if ($paymentMethod === 'credit_card') {
                    $paymentRequestData['card_token'] = $paymentData['card_token'] ?? null;
                    $paymentRequestData['installments'] = $paymentData['installments'] ?? 1;
                    unset($paymentRequestData['payment_method_id']);
                }

                $paymentRequest = \App\Domain\Payment\ValueObjects\PaymentRequest::fromArray($paymentRequestData);

                // Gerar chave de idempotência
                $timeWindow = date('YmdHi'); // Resolução 1 min
                $idempotencyKey = hash('sha256', 'plan_change_' . $tenant->id . '_' . $novaAssinatura->id . '_' . $timeWindow);

                // Processar pagamento com tratamento de erro robusto
                try {
                    $paymentResult = $this->paymentProvider->processPayment($paymentRequest, $idempotencyKey);
                } catch (\Exception $e) {
                    \Log::error('Erro ao processar pagamento de troca de plano - Cancelando assinatura parcial', [
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $novaAssinatura->id,
                        'erro' => $e->getMessage()
                    ]);
                    
                    // 🔥 SEGURO: Se falhar o processamento, cancelar a assinatura nova que está pendente
                    // para não bloquear o usuário em "já estou neste plano" nas próximas tentativas
                    $novaAssinatura->update([
                        'status' => 'cancelada',
                        'observacoes' => "Falha no processamento do pagamento: " . $e->getMessage()
                    ]);
                    
                    throw $e;
                }

                // Salvar log de pagamento (opcional, mas bom ter para PIX)
                $paymentLog = \App\Models\PaymentLog::create([
                    'tenant_id' => $tenant->id,
                    'plano_id' => $novoPlanoId,
                    'valor' => $valorCobrar,
                    'periodo' => $periodo ?? 'mensal',
                    'status' => $paymentResult->status,
                    'external_id' => $paymentResult->externalId,
                    'idempotency_key' => $paymentResult->externalId ?? uniqid('pmt_', true),
                    'metodo_pagamento' => $paymentResult->paymentMethod,
                    'dados_resposta' => array_merge([
                        'status' => $paymentResult->status,
                        'payment_method' => $paymentResult->paymentMethod,
                        'error_message' => $paymentResult->errorMessage,
                    ], array_filter([
                        'pix_qr_code' => $paymentResult->pixQrCode,
                        'pix_qr_code_base64' => $paymentResult->pixQrCodeBase64,
                        'pix_ticket_url' => $paymentResult->pixTicketUrl,
                    ])),
                ]);

                // Se aprovado, ativar assinatura
                if ($paymentResult->isApproved()) {
                    $novaAssinatura->update([
                        'status' => 'ativa',
                        'metodo_pagamento' => $paymentResult->paymentMethod,
                        'transacao_id' => $paymentResult->externalId,
                    ]);

                    // 🔥 CRÍTICO: Cancelar assinatura antiga se o pagamento foi aprovado
                    if (isset($resultado['assinatura_antiga']) && (int)$resultado['assinatura_antiga']->id !== (int)$novaAssinatura->id) {
                        $antiga = $resultado['assinatura_antiga'];
                        $antiga->update([
                            'status' => 'cancelada',
                            'data_cancelamento' => now(),
                            'observacoes' => ($antiga->observacoes ?? '') . "\n\nCancelada após upgrade com cartão em " . now()->format('Y-m-d H:i:s'),
                        ]);
                        
                        // Atualizar tenant
                        $tenantModel = \App\Models\Tenant::find($tenant->id);
                        if ($tenantModel) {
                            $tenantModel->update([
                                'plano_atual_id' => $novoPlanoId,
                                'assinatura_atual_id' => $novaAssinatura->id,
                            ]);
                        }
                    }
                } elseif ($paymentResult->isPending()) {
                    $novaAssinatura->update([
                        'status' => 'aguardando_pagamento',
                        'metodo_pagamento' => $paymentResult->paymentMethod,
                        'transacao_id' => $paymentResult->externalId,
                        'observacoes' => ($novaAssinatura->observacoes ?? '') . "\nPagamento pendente (" . $paymentMethod . ") - aguardando confirmação. ID: " . $paymentResult->externalId,
                    ]);
                } else {
                    // Se estiver rejeitado, marcar a nova assinatura como cancelada para não travar
                    $novaAssinatura->update(['status' => 'cancelada']);
                    
                    throw new \App\Domain\Exceptions\DomainException(
                        $paymentResult->errorMessage ?? 'Pagamento rejeitado pelo gateway.'
                    );
                }
            }

            // Buscar entidade atualizada e transformar em DTO
            $assinaturaDomain = $this->assinaturaRepository->buscarPorId($novaAssinatura->id);
            $responseData = [];

            if ($assinaturaDomain) {
                $responseDTO = $this->assinaturaResource->toResponse($assinaturaDomain);
                $responseData = $responseDTO->toArray();
            } else {
                $responseData = [
                    'id' => $novaAssinatura->id,
                    'status' => $novaAssinatura->status,
                ];
            }

            // 🔥 MELHORIA: Garantir inclusão dos dados PIX na resposta se disponível
            if (isset($paymentResult) && $paymentResult->paymentMethod === 'pix' && $novaAssinatura->status !== 'ativa') {
                $responseData['pix_qr_code_base64'] = $paymentResult->pixQrCodeBase64;
                $responseData['pix_qr_code'] = $paymentResult->pixQrCode;
                $responseData['pix_ticket_url'] = $paymentResult->pixTicketUrl;
                $responseData['payment_id'] = $paymentResult->externalId;
                $responseData['amount'] = (float) $valorCobrar;
                
                // Backup via log se necessário
                if (!$responseData['pix_qr_code'] && isset($paymentLog)) {
                    $dados = $paymentLog->dados_resposta ?? [];
                    $responseData['pix_qr_code'] = $dados['pix_qr_code'] ?? null;
                    $responseData['pix_qr_code_base64'] = $dados['pix_qr_code_base64'] ?? null;
                }
            }

            return response()->json([
                'message' => 'Plano alterado com sucesso',
                'data' => $responseData,
                'credito_aplicado' => $credito,
                'valor_cobrado' => $valorCobrar,
                'pending' => $novaAssinatura->status !== 'ativa',
            ], 200);

        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao trocar plano');
        }
    }
    /**
     * Simula troca de plano para mostrar valores de pro-rata ao usuário
     */
    public function simularTrocaPlano(Request $request): JsonResponse
    {
        try {
            $tenant = $this->getTenantOrFail();
            $planoId = $request->input('plano_id');
            $periodo = $request->input('periodo', 'mensal');

            if (!$planoId) {
                return response()->json(['message' => 'Plano não informado'], 400);
            }

            $resultado = $this->trocarPlanoAssinaturaUseCase->simular($tenant->id, (int)$planoId, $periodo);

            return response()->json([
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao simular troca de plano');
        }
    }

    /**
     * Histórico de pagamentos da assinatura
     * Retorna todas as transações de pagamento do usuário/empresa
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function historicoPagamentos(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não autenticado',
                ], 401);
            }

            // Buscar empresa ativa do usuário
            $empresaId = $user->empresa_ativa_id ?? $request->header('X-Empresa-ID');
            
            if (!$empresaId) {
                return response()->json([
                    'message' => 'Empresa não identificada',
                    'data' => []
                ]);
            }

            // Buscar tenant da empresa
            $empresa = \App\Models\Empresa::find($empresaId);
            
            if (!$empresa) {
                return response()->json([
                    'message' => 'Empresa não encontrada',
                    'data' => []
                ]);
            }

            $tenantId = $empresa->tenant_id;

            // Buscar todas as assinaturas (histórico de pagamentos) do tenant
            $assinaturas = $this->assinaturaRepository->listarPorTenant($tenantId);

            // Transformar em formato de histórico de pagamentos
            $historico = collect($assinaturas)->map(function ($assinatura) {
                return [
                    'id' => $assinatura->id,
                    'data' => $assinatura->data_inicio,
                    'data_fim' => $assinatura->data_fim,
                    'valor' => (float) $assinatura->valor_pago,
                    'metodo' => $assinatura->metodo_pagamento,
                    'status' => $assinatura->status,
                    'transacao_id' => $assinatura->transacao_id,
                    'descricao' => $assinatura->plano 
                        ? "Plano {$assinatura->plano->nome}" 
                        : "Assinatura #{$assinatura->id}",
                    'plano' => $assinatura->plano ? [
                        'id' => $assinatura->plano->id,
                        'nome' => $assinatura->plano->nome,
                    ] : null,
                ];
            })->sortByDesc('data')->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => $historico,
                'meta' => [
                    'total' => count($historico),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar histórico de pagamentos');
        }
    }
}

