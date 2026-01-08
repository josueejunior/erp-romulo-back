<?php

namespace App\Modules\Calendario\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Calendario\DTOs\BuscarCalendarioDisputasDTO;
use App\Application\Calendario\DTOs\BuscarCalendarioJulgamentoDTO;
use App\Application\Calendario\UseCases\BuscarCalendarioDisputasUseCase;
use App\Application\Calendario\UseCases\BuscarCalendarioJulgamentoUseCase;
use App\Application\Calendario\UseCases\BuscarAvisosUrgentesUseCase;
use App\Application\Calendario\Presenters\CalendarioPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller do Calendário
 * 
 * ✅ DDD: Usa Use Cases, DTOs e Presenter
 * 
 * Fluxo:
 * Request → DTO → Use Case → Presenter → Response
 */
class CalendarioController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private BuscarCalendarioDisputasUseCase $buscarCalendarioDisputasUseCase,
        private BuscarCalendarioJulgamentoUseCase $buscarCalendarioJulgamentoUseCase,
        private BuscarAvisosUrgentesUseCase $buscarAvisosUrgentesUseCase,
    ) {}

    /**
     * Retorna calendário de disputas
     * 
     * ✅ DDD: Usa Use Case e Presenter
     */
    public function disputas(Request $request): JsonResponse
    {
        \Log::info('CalendarioController::disputas - ✅ Método chamado (DDD)', [
            'request_params' => $request->all(),
        ]);

        // 1. Verificar acesso ao plano
        $tenant = $this->getTenant();
        if (!$tenant || !$tenant->temAcessoCalendario()) {
            \Log::warning('CalendarioController::disputas - Acesso negado pelo plano');
            return response()->json(CalendarioPresenter::erroAcessoPlano(), 403);
        }

        try {
            // 2. Obter contexto
            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = $this->getTenantId();
            \Log::debug('CalendarioController::disputas - Contexto obtido', [
                'empresa_id' => $empresa->id,
                'tenant_id' => $tenantId,
            ]);
            
            // 3. Criar DTO a partir do Request
            $dto = BuscarCalendarioDisputasDTO::fromRequest(
                $request->all(),
                $empresa->id
            );
            \Log::debug('CalendarioController::disputas - DTO criado', [
                'empresa_id' => $dto->empresaId,
                'data_inicio' => $dto->getDataInicioFormatted(),
                'data_fim' => $dto->getDataFimFormatted(),
            ]);
            
            // 4. Executar Use Case
            $eventos = $this->buscarCalendarioDisputasUseCase->executar($dto, $tenantId);
            \Log::debug('CalendarioController::disputas - Use Case executado', [
                'total_eventos' => $eventos->count(),
            ]);
            
            // 5. Formatar resposta via Presenter
            return response()->json(CalendarioPresenter::formatarDisputas($eventos));
            
        } catch (\Exception $e) {
            \Log::error('CalendarioController::disputas - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Erro ao buscar calendário de disputas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna calendário de julgamento
     * 
     * ✅ DDD: Usa Use Case e Presenter
     */
    public function julgamento(Request $request): JsonResponse
    {
        \Log::info('CalendarioController::julgamento - ✅ Método chamado (DDD)', [
            'request_params' => $request->all(),
        ]);

        // 1. Verificar acesso ao plano
        $tenant = $this->getTenant();
        if (!$tenant || !$tenant->temAcessoCalendario()) {
            \Log::warning('CalendarioController::julgamento - Acesso negado pelo plano');
            return response()->json(CalendarioPresenter::erroAcessoPlano(), 403);
        }

        try {
            // 2. Obter contexto
            $empresa = $this->getEmpresaAtivaOrFail();
            \Log::debug('CalendarioController::julgamento - Empresa obtida', [
                'empresa_id' => $empresa->id,
            ]);
            
            // 3. Criar DTO a partir do Request
            $dto = BuscarCalendarioJulgamentoDTO::fromRequest(
                $request->all(),
                $empresa->id
            );
            \Log::debug('CalendarioController::julgamento - DTO criado', [
                'empresa_id' => $dto->empresaId,
                'data_inicio' => $dto->getDataInicioFormatted(),
                'data_fim' => $dto->getDataFimFormatted(),
            ]);
            
            // 4. Executar Use Case
            $processos = $this->buscarCalendarioJulgamentoUseCase->executar($dto);
            \Log::debug('CalendarioController::julgamento - Use Case executado', [
                'total_processos' => $processos->count(),
            ]);
            
            // 5. Formatar resposta via Presenter
            return response()->json(CalendarioPresenter::formatarJulgamentos($processos));
            
        } catch (\Exception $e) {
            \Log::error('CalendarioController::julgamento - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Erro ao buscar calendário de julgamento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna avisos urgentes
     * 
     * ✅ DDD: Usa Use Case e Presenter
     */
    public function avisosUrgentes(): JsonResponse
    {
        // 1. Verificar acesso ao plano
        $tenant = $this->getTenant();
        if (!$tenant || !$tenant->temAcessoCalendario()) {
            return response()->json(CalendarioPresenter::erroAcessoPlano(), 403);
        }

        try {
            // 2. Obter contexto
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // 3. Executar Use Case
            $avisos = $this->buscarAvisosUrgentesUseCase->executar($empresa->id);
            
            // 4. Formatar resposta via Presenter
            return response()->json(CalendarioPresenter::formatarAvisosUrgentes($avisos));
            
        } catch (\Exception $e) {
            \Log::error('CalendarioController::avisosUrgentes - Erro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Erro ao buscar avisos urgentes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
