<?php

namespace App\Modules\Processo\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Services\SaldoService;
use App\Services\RedisService;
use App\Modules\Processo\Models\Processo;
use Illuminate\Http\Request;

class SaldoController extends BaseApiController
{
    protected SaldoService $saldoService;

    public function __construct(SaldoService $saldoService)
    {
        $this->saldoService = $saldoService;
    }

    /**
     * Retorna saldo completo do processo
     */
    public function show(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->saldoService->validarProcessoEmpresa($processo, $empresa->id);
            $this->saldoService->validarProcessoEmExecucao($processo);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Apenas processos em execução possuem saldo.' ? 403 : 404);
        }

        $tenantId = tenancy()->tenant?->id;
        
        // Tentar obter do cache primeiro
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::getSaldo($tenantId, $processo->id);
            if ($cached !== null) {
                return response()->json([
                    'data' => $cached,
                ]);
            }
        }

        $saldo = $this->saldoService->calcularSaldoCompleto($processo);

        // Salvar no cache se disponível
        if ($tenantId && RedisService::isAvailable()) {
            RedisService::cacheSaldo($tenantId, $processo->id, $saldo, 600); // Cache por 10 minutos
        }

        return response()->json([
            'data' => $saldo,
        ]);
    }

    /**
     * Retorna apenas saldo vencido
     */
    public function saldoVencido(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->saldoService->validarProcessoEmpresa($processo, $empresa->id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
        
        $saldo = $this->saldoService->calcularSaldoVencido($processo);

        return response()->json([
            'data' => $saldo,
        ]);
    }

    /**
     * Retorna saldo vinculado
     */
    public function saldoVinculado(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->saldoService->validarProcessoEmpresa($processo, $empresa->id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
        
        $saldo = $this->saldoService->calcularSaldoVinculado($processo);

        return response()->json([
            'data' => $saldo,
        ]);
    }

    /**
     * Retorna saldo empenhado
     */
    public function saldoEmpenhado(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->saldoService->validarProcessoEmpresa($processo, $empresa->id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
        
        $saldo = $this->saldoService->calcularSaldoEmpenhado($processo);

        return response()->json([
            'data' => $saldo,
        ]);
    }
}





