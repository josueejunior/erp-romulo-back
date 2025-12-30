<?php

namespace App\Modules\Assinatura\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Assinatura\UseCases\BuscarAssinaturaAtualUseCase;
use App\Application\Assinatura\UseCases\ObterStatusAssinaturaUseCase;
use App\Application\Assinatura\UseCases\ListarAssinaturasUseCase;
use App\Application\Assinatura\UseCases\CancelarAssinaturaUseCase;
use App\Application\Payment\UseCases\RenovarAssinaturaUseCase;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Http\Requests\Assinatura\RenovarAssinaturaRequest;
use App\Http\Requests\Assinatura\CriarAssinaturaRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para Assinaturas
 * 
 * Refatorado para usar DDD (Domain-Driven Design)
 * Organizado por módulo seguindo Arquitetura Hexagonal
 */
class AssinaturaController extends BaseApiController
{
    public function __construct(
        private BuscarAssinaturaAtualUseCase $buscarAssinaturaAtualUseCase,
        private ObterStatusAssinaturaUseCase $obterStatusAssinaturaUseCase,
        private ListarAssinaturasUseCase $listarAssinaturasUseCase,
        private CancelarAssinaturaUseCase $cancelarAssinaturaUseCase,
        private RenovarAssinaturaUseCase $renovarAssinaturaUseCase,
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Retorna assinatura atual do tenant
     * Permite acesso mesmo sem assinatura (retorna null) para que o frontend possa tratar
     */
    public function atual(Request $request): JsonResponse
    {
        try {
            $tenant = tenancy()->tenant;
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Tentar buscar assinatura, mas não lançar erro se não encontrar
            try {
                $assinatura = $this->buscarAssinaturaAtualUseCase->executar($tenant->id);
                
                // Buscar modelo para resposta (mantém compatibilidade com frontend)
                $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinatura->id);

                if (!$assinaturaModel) {
                    return response()->json([
                        'data' => null,
                        'message' => 'Assinatura não encontrada',
                        'code' => 'NO_SUBSCRIPTION'
                    ], 200);
                }

                // Calcular dias restantes usando o modelo (mantém compatibilidade)
                $diasRestantes = $assinaturaModel->diasRestantes();

                return response()->json([
                    'data' => [
                        'id' => $assinaturaModel->id,
                        'tenant_id' => $assinaturaModel->tenant_id,
                        'plano_id' => $assinaturaModel->plano_id,
                        'status' => $assinaturaModel->status,
                        'data_inicio' => $assinaturaModel->data_inicio ? $assinaturaModel->data_inicio->format('Y-m-d') : null,
                        'data_fim' => $assinaturaModel->data_fim ? $assinaturaModel->data_fim->format('Y-m-d') : null,
                        'valor_pago' => $assinaturaModel->valor_pago,
                        'metodo_pagamento' => $assinaturaModel->metodo_pagamento,
                        'transacao_id' => $assinaturaModel->transacao_id,
                        'dias_restantes' => $diasRestantes,
                        'plano' => $assinaturaModel->plano ? [
                            'id' => $assinaturaModel->plano->id,
                            'nome' => $assinaturaModel->plano->nome,
                            'descricao' => $assinaturaModel->plano->descricao,
                            'preco_mensal' => $assinaturaModel->plano->preco_mensal,
                            'preco_anual' => $assinaturaModel->plano->preco_anual,
                            'limite_processos' => $assinaturaModel->plano->limite_processos,
                            'limite_usuarios' => $assinaturaModel->plano->limite_usuarios,
                            'limite_armazenamento_mb' => $assinaturaModel->plano->limite_armazenamento_mb,
                        ] : null,
                    ]
                ]);
            } catch (\App\Domain\Exceptions\NotFoundException $e) {
                // Não há assinatura - retornar null para que o frontend possa tratar
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
     * Permite acesso mesmo sem assinatura (retorna null) para que o frontend possa tratar
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $tenant = tenancy()->tenant;
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Tentar obter empresa, mas não falhar se não houver (para permitir consulta de status)
            $empresaId = null;
            try {
                $empresa = $this->getEmpresaAtivaOrFail();
                $empresaId = $empresa->id;
            } catch (\Exception $e) {
                // Se não conseguir obter empresa, continuar sem ela (para contagem de usuários)
                \Log::debug('AssinaturaController::status() - Não foi possível obter empresa ativa', [
                    'error' => $e->getMessage()
                ]);
            }
            
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
                        'tenant_id' => $tenant->id,
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
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenant = tenancy()->tenant;
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Preparar filtros
            $filtros = [];
            if ($request->has('status')) {
                $filtros['status'] = $request->status;
            }

            // Executar Use Case
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
     * 
     * Nota: Assinaturas normalmente são criadas via PaymentController::processarAssinatura()
     * Este método é para casos especiais (ex: admin criar assinatura gratuita)
     * Usa Form Request para validação
     */
    public function store(CriarAssinaturaRequest $request): JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Buscar plano
            $plano = \App\Modules\Assinatura\Models\Plano::find($validated['plano_id']);
            if (!$plano) {
                return response()->json(['message' => 'Plano não encontrado'], 404);
            }

            // Criar assinatura
            $assinatura = \App\Modules\Assinatura\Models\Assinatura::create([
                'tenant_id' => $tenant->id,
                'plano_id' => $validated['plano_id'],
                'status' => $validated['status'] ?? 'ativa',
                'data_inicio' => $validated['data_inicio'] ?? now(),
                'data_fim' => $validated['data_fim'],
                'valor_pago' => $validated['valor_pago'] ?? 0,
                'metodo_pagamento' => $validated['metodo_pagamento'] ?? 'gratuito',
                'dias_grace_period' => 7,
            ]);

            // Atualizar tenant se for a primeira assinatura ou se não tiver assinatura atual
            if (!$tenant->assinatura_atual_id || $validated['status'] === 'ativa') {
                $tenant->update([
                    'plano_atual_id' => $plano->id,
                    'assinatura_atual_id' => $assinatura->id,
                ]);
            }

            return response()->json([
                'message' => 'Assinatura criada com sucesso',
                'data' => [
                    'id' => $assinatura->id,
                    'status' => $assinatura->status,
                    'data_fim' => $assinatura->data_fim->format('Y-m-d'),
                ],
            ], 201);
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
     * Usa Form Request para validação
     */
    public function renovar(RenovarAssinaturaRequest $request, $assinatura): JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Buscar assinatura usando repository (DDD)
            $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinatura);
            if (!$assinaturaModel) {
                return response()->json(['message' => 'Assinatura não encontrada'], 404);
            }

            // Validar que a assinatura pertence ao tenant
            if ($assinaturaModel->tenant_id !== $tenant->id) {
                return response()->json(['message' => 'Assinatura não encontrada'], 404);
            }

            // Carregar plano
            $plano = $assinaturaModel->plano;
            if (!$plano) {
                return response()->json(['message' => 'Plano da assinatura não encontrado'], 404);
            }

            // Calcular valor
            $meses = $validated['meses'];
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

            return response()->json([
                'message' => 'Assinatura renovada com sucesso',
                'data' => [
                    'assinatura_id' => $assinaturaRenovada->id,
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Assinatura não encontrada',
            ], 404);
        } catch (\App\Domain\Exceptions\DomainException | \App\Domain\Exceptions\BusinessRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao renovar assinatura', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao renovar assinatura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela assinatura
     */
    public function cancelar(Request $request, $assinatura): JsonResponse
    {
        try {
            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Executar Use Case
            $assinaturaCancelada = $this->cancelarAssinaturaUseCase->executar($tenant->id, $assinatura);

            return response()->json([
                'message' => 'Assinatura cancelada com sucesso',
                'data' => [
                    'id' => $assinaturaCancelada->id,
                    'status' => $assinaturaCancelada->status,
                    'data_cancelamento' => $assinaturaCancelada->data_cancelamento?->format('Y-m-d H:i:s'),
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
}

