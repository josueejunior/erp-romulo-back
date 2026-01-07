<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Dashboard\UseCases\ObterDadosDashboardUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para Dashboard
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Use Case para lógica de negócio
 * - Validação de acesso ao dashboard (deveria estar em middleware, mas mantido aqui por compatibilidade)
 * - Cache gerenciado pelo Use Case
 * - Controller fino: apenas recebe request e retorna response
 */
class DashboardController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private ObterDadosDashboardUseCase $obterDadosDashboardUseCase,
    ) {}

    /**
     * API: Obter dados do dashboard
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Valida acesso ao dashboard (baseado no plano)
     * - Obtém empresa e tenant do contexto
     * - Chama Use Case para obter dados
     * - Retorna resposta JSON
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não gerencia cache (Use Case faz isso)
     * - Não acessa repositories diretamente
     * - Não contém lógica de negócio
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validar acesso ao dashboard (baseado no plano do tenant)
            $tenant = $this->getTenant();
            if (!$tenant || !$tenant->temAcessoDashboard()) {
                return response()->json([
                    'message' => 'O dashboard não está disponível no seu plano. Faça upgrade para o plano Profissional ou superior.',
                ], 403);
            }

            // Obter empresa e tenant do contexto (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = $this->getTenantId();
            
            // Executar Use Case (gerencia cache internamente)
            $data = $this->obterDadosDashboardUseCase->executar($empresa->id, $tenantId);

            return response()->json($data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao obter dados do dashboard');
        }
    }
}

