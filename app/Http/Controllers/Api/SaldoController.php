<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SaldoService;
use App\Services\RedisService;
use App\Models\Processo;
use Illuminate\Http\Request;

class SaldoController extends Controller
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
        if (!$processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Apenas processos em execução possuem saldo.'
            ], 403);
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
        $saldo = $this->saldoService->calcularSaldoEmpenhado($processo);

        return response()->json([
            'data' => $saldo,
        ]);
    }
}




