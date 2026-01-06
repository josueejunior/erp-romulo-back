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
 * 
 * 🔥 IMPORTANTE: Este controller busca o tenant correto baseado na empresa ativa,
 * não apenas o tenant do header (que pode estar desatualizado).
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
        private PaymentProviderInterface $paymentProvider,
        private AssinaturaResource $assinaturaResource,
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Obtém empresa_id do contexto (automático via BaseApiController)
     * Retorna null se não conseguir obter (para permitir consulta de status sem empresa)
     */
    protected function getEmpresaIdOrNull(): ?int
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            return $empresa->id;
        } catch (\Exception $e) {
            // Se não conseguir obter empresa, retornar null (para permitir consulta de status)
            \Log::debug('AssinaturaController::getEmpresaIdOrNull() - Não foi possível obter empresa ativa', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca o tenant correto baseado na empresa ativa do usuário
     * 
     * 🔥 CRÍTICO: Este método garante que sempre busquemos a assinatura no tenant correto,
     * mesmo se o header X-Tenant-ID estiver desatualizado.
     * 
     * Prioridades:
     * 1. Verificar se empresa ativa existe no tenant atual (otimização)
     * 2. Buscar empresa em outros tenants (se não encontrou no atual)
     * 3. Tenant do header X-Tenant-ID (fallback)
     * 4. Tenant do contexto tenancy (último recurso)
     * 
     * @return \App\Models\Tenant|null
     */
    protected function getTenantCorretoDaEmpresaAtiva(): ?\App\Models\Tenant
    {
        try {
            // Prioridade 1: Tentar obter empresa ativa
            $empresaId = $this->getEmpresaIdOrNull();
            
            if (!$empresaId) {
                // Se não tem empresa, usar tenant do contexto
                $tenant = $this->getTenant();
                \Log::debug('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Sem empresa ativa, usando tenant do contexto', [
                    'tenant_id' => $tenant?->id,
                ]);
                return $tenant;
            }
            
            // Prioridade 2: Verificar se empresa existe no tenant atual (otimização)
            $tenantAtual = tenancy()->tenant;
            if ($tenantAtual && tenancy()->initialized) {
                try {
                    $empresa = \App\Models\Empresa::find($empresaId);
                    if ($empresa) {
                        \Log::info('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Empresa encontrada no tenant atual', [
                            'empresa_id' => $empresaId,
                            'tenant_id' => $tenantAtual->id,
                        ]);
                        return $tenantAtual;
                    }
                } catch (\Exception $e) {
                    \Log::debug('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Erro ao buscar no tenant atual', [
                        'tenant_id' => $tenantAtual->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Prioridade 3: Buscar empresa em outros tenants (se não encontrou no atual)
            $tenants = \App\Models\Tenant::all();
            
            foreach ($tenants as $tenant) {
                // Pular o tenant atual (já verificamos)
                if ($tenantAtual && $tenant->id === $tenantAtual->id) {
                    continue;
                }
                
                try {
                    tenancy()->initialize($tenant);
                    $empresa = \App\Models\Empresa::find($empresaId);
                    
                    if ($empresa) {
                        // Encontrou a empresa neste tenant - este é o tenant correto
                        tenancy()->end();
                        
                        \Log::info('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Tenant encontrado via empresa em outro tenant', [
                            'empresa_id' => $empresaId,
                            'tenant_id' => $tenant->id,
                            'tenant_razao_social' => $tenant->razao_social,
                            'tenant_atual_anterior' => $tenantAtual?->id,
                        ]);
                        
                        return $tenant;
                    }
                    
                    tenancy()->end();
                } catch (\Exception $e) {
                    tenancy()->end();
                    \Log::debug('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Erro ao buscar no tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::debug('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Erro ao buscar tenant via empresa', [
                'error' => $e->getMessage(),
            ]);
        }
        
        // Prioridade 4: Fallback para tenant do header/contexto
        $tenant = $this->getTenant();
        
        if ($tenant) {
            \Log::info('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Usando tenant do contexto (fallback)', [
                'tenant_id' => $tenant->id,
                'tenant_razao_social' => $tenant->razao_social,
            ]);
            return $tenant;
        }
        
        \Log::warning('AssinaturaController::getTenantCorretoDaEmpresaAtiva() - Nenhum tenant encontrado');
        return null;
    }


    /**
     * Retorna assinatura atual do tenant
     * Retorna entidade de domínio transformada via Resource
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Busca tenant correto baseado na empresa ativa
     * - Chama Use Case para buscar assinatura
     * - Transforma entidade em DTO de resposta
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header (usa getTenantCorretoDaEmpresaAtiva)
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * 🔥 IMPORTANTE: Busca o tenant correto baseado na empresa ativa do usuário,
     * não apenas o tenant do header (que pode estar desatualizado).
     * Permite acesso mesmo sem assinatura (retorna null) para que o frontend possa tratar.
     */
    public function atual(Request $request): JsonResponse
    {
        
            // 🔥 CRÍTICO: Buscar tenant correto baseado na empresa ativa
            // Isso garante que mesmo se o header X-Tenant-ID estiver desatualizado,
            // ainda buscaremos a assinatura no tenant correto da empresa ativa

            $tenant = $this->getTenantCorretoDaEmpresaAtiva();

            // Salvar no log qual id está sendo utilizado
            if ($tenant) {
                \Log::info('AssinaturaController::atual() - Tenant ID utilizado', [
                    'tenant_id' => $tenant->id,
                ]);
            } else {
                \Log::warning('AssinaturaController::atual() - Nenhum tenant encontrado');
            }
       
                $assinatura = $this->buscarAssinaturaAtualUseCase->executar($tenant->id);
                
                // Transformar entidade do domínio em DTO de resposta
                $responseDTO = $this->assinaturaResource->toResponse($assinatura);

                return response()->json([
                    'data' => $responseDTO->toArray()
                ]);
             
    }

    /**
     * Retorna status da assinatura com limites utilizados
     * Retorna dados de status e limites utilizados
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Busca tenant correto baseado na empresa ativa
     * - Obtém empresa_id automaticamente (pode ser null)
     * - Chama Use Case para obter status
     * - Retorna dados de status e limites
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente do header (usa getTenantCorretoDaEmpresaAtiva)
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * 🔥 IMPORTANTE: Busca o tenant correto baseado na empresa ativa do usuário,
     * não apenas o tenant do header (que pode estar desatualizado).
     * Permite acesso mesmo sem assinatura (retorna null) para que o frontend possa tratar.
     */
    public function status(Request $request): JsonResponse
    {
        try {
            // 🔥 CRÍTICO: Buscar tenant correto baseado na empresa ativa
            $tenant = $this->getTenantCorretoDaEmpresaAtiva();
            
            if (!$tenant) {
                // Se não conseguir determinar o tenant, retornar dados vazios
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
            
            // Obter empresa_id automaticamente (pode ser null para permitir consulta de status)
            $empresaId = $this->getEmpresaIdOrNull();
            
            // Tentar buscar status, mas não lançar erro se não encontrar assinatura
            try {
                // Se não tem empresa, usar 0 como fallback para contagem de usuários
                $statusData = $this->obterStatusAssinaturaUseCase->executar($tenant->id, $empresaId ?? 0);

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

            // Calcular valor
            $meses = $validated['meses'];
            $plano = $assinaturaModel->plano;
            $valor = $meses === 12 && $plano->preco_anual 
                ? $plano->preco_anual 
                : $plano->preco_mensal * $meses;

            // Criar PaymentRequest
            $paymentRequest = \App\Domain\Payment\ValueObjects\PaymentRequest::fromArray([
                'amount' => $valor,
                'description' => "Renovação de assinatura - Plano {$plano->nome} - {$meses} " . ($meses === 1 ? 'mês' : 'meses'),
                'payer_email' => $validated['payer_email'],
                'payer_cpf' => $validated['payer_cpf'] ?? null,
                'card_token' => $validated['card_token'],
                'installments' => $validated['installments'] ?? 1,
                'payment_method_id' => 'credit_card',
                'external_reference' => "renewal_tenant_{$tenant->id}_assinatura_{$assinaturaModel->id}",
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'assinatura_id' => $assinaturaModel->id,
                    'plano_id' => $plano->id,
                    'meses' => $meses,
                ],
            ]);

            // Processar renovação usando Use Case injetado
            $assinaturaRenovada = $this->renovarAssinaturaUseCase->executar(
                $assinaturaModel,
                $paymentRequest,
                $meses
            );

            // Buscar entidade renovada e transformar em DTO
            $assinaturaRenovadaDomain = $this->assinaturaRepository->buscarPorId($assinaturaRenovada->id);
            if ($assinaturaRenovadaDomain) {
                $responseDTO = $this->assinaturaResource->toResponse($assinaturaRenovadaDomain);
                
                return response()->json([
                    'message' => 'Assinatura renovada com sucesso',
                    'data' => $responseDTO->toArray(),
                ], 200);
            }

            // Fallback: retornar dados do modelo se não conseguir buscar entidade
            return response()->json([
                'message' => 'Assinatura renovada com sucesso',
                'data' => [
                    'id' => $assinaturaRenovada->id,
                    'status' => $assinaturaRenovada->status,
                    'data_fim' => $assinaturaRenovada->data_fim->format('Y-m-d'),
                    'dias_restantes' => $assinaturaRenovada->diasRestantes(),
                ],
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
                
                // Criar PaymentRequest
                $paymentRequest = \App\Domain\Payment\ValueObjects\PaymentRequest::fromArray([
                    'amount' => $valorCobrar,
                    'description' => "Troca de plano - Crédito aplicado: R$ {$credito}",
                    'payer_email' => $paymentData['payer_email'],
                    'payer_cpf' => $paymentData['payer_cpf'] ?? null,
                    'card_token' => $paymentData['card_token'],
                    'installments' => $paymentData['installments'] ?? 1,
                    'payment_method_id' => 'credit_card',
                    'external_reference' => "plan_change_tenant_{$tenant->id}_assinatura_{$novaAssinatura->id}",
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $novaAssinatura->id,
                        'plano_id' => $novoPlanoId,
                        'credito_aplicado' => $credito,
                        'tipo' => 'troca_plano',
                    ],
                ]);

                // Gerar chave de idempotência
                $idempotencyKey = 'plan_change_' . $tenant->id . '_' . $novaAssinatura->id . '_' . time();

                // Processar pagamento
                $paymentResult = $this->paymentProvider->processPayment($paymentRequest, $idempotencyKey);

                // Se aprovado, ativar assinatura
                if ($paymentResult->isApproved()) {
                    $novaAssinatura->update([
                        'status' => 'ativa',
                        'metodo_pagamento' => $paymentResult->paymentMethod,
                        'transacao_id' => $paymentResult->externalId,
                    ]);
                } elseif ($paymentResult->isPending()) {
                    // Se pendente (ex: PIX), manter como pendente
                    $novaAssinatura->update([
                        'status' => 'pendente',
                        'transacao_id' => $paymentResult->externalId,
                        'observacoes' => ($novaAssinatura->observacoes ?? '') . "\nPagamento pendente - aguardando confirmação.",
                    ]);
                } else {
                    // Se rejeitado, lançar exceção
                    throw new \App\Domain\Exceptions\DomainException(
                        $paymentResult->errorMessage ?? 'Pagamento rejeitado pelo gateway.'
                    );
                }
            }

            // Buscar entidade atualizada e transformar em DTO
            $assinaturaDomain = $this->assinaturaRepository->buscarPorId($novaAssinatura->id);
            if ($assinaturaDomain) {
                $responseDTO = $this->assinaturaResource->toResponse($assinaturaDomain);
                
                return response()->json([
                    'message' => 'Plano alterado com sucesso',
                    'data' => $responseDTO->toArray(),
                    'credito_aplicado' => $credito,
                    'valor_cobrado' => $valorCobrar,
                ], 200);
            }

            // Fallback
            return response()->json([
                'message' => 'Plano alterado com sucesso',
                'data' => [
                    'id' => $novaAssinatura->id,
                    'status' => $novaAssinatura->status,
                ],
                'credito_aplicado' => $credito,
                'valor_cobrado' => $valorCobrar,
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
}

