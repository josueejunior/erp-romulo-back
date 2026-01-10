<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\AfiliadoIndicacao;
use App\Modules\Assinatura\Models\Assinatura;
use App\Models\AfiliadoComissaoRecorrente;
use App\Models\Tenant;
use App\Domain\Afiliado\Events\ComissaoGerada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Use Case: Calcular Comissão Recorrente
 * 
 * Calcula e registra comissão recorrente para cada ciclo de 30 dias
 * quando o pagamento é confirmado
 */
final class CalcularComissaoRecorrenteUseCase
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Calcula e registra comissão recorrente para uma assinatura
     * 
     * @param int $tenantId ID do tenant
     * @param int $assinaturaId ID da assinatura
     * @param float $valorPago Valor efetivamente pago pelo cliente
     * @param Carbon $dataPagamento Data do pagamento
     * @return AfiliadoComissaoRecorrente|null
     */
    public function executar(
        int $tenantId,
        int $assinaturaId,
        float $valorPago,
        Carbon $dataPagamento
    ): ?AfiliadoComissaoRecorrente {
        return DB::transaction(function () use ($tenantId, $assinaturaId, $valorPago, $dataPagamento) {
            // Buscar tenant
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                Log::warning('CalcularComissaoRecorrenteUseCase - Tenant não encontrado', [
                    'tenant_id' => $tenantId,
                ]);
                return null;
            }

            // Inicializar contexto do tenant
            tenancy()->initialize($tenant);

            try {
                // Buscar assinatura
                $assinatura = Assinatura::with('plano')->find($assinaturaId);
                if (!$assinatura) {
                    Log::warning('CalcularComissaoRecorrenteUseCase - Assinatura não encontrada', [
                        'tenant_id' => $tenantId,
                        'assinatura_id' => $assinaturaId,
                    ]);
                    return null;
                }

                // Buscar indicação de afiliado para esta empresa
                $indicacao = AfiliadoIndicacao::where('tenant_id', $tenantId)
                    ->where('empresa_id', $assinatura->empresa_id)
                    ->where('status', 'ativa')
                    ->first();

                if (!$indicacao) {
                    Log::debug('CalcularComissaoRecorrenteUseCase - Nenhuma indicação de afiliado encontrada', [
                        'tenant_id' => $tenantId,
                        'empresa_id' => $assinatura->empresa_id,
                    ]);
                    return null;
                }

                // Calcular ciclo de 30 dias baseado na data de início da assinatura
                $dataInicioCiclo = $assinatura->data_inicio->copy();
                $dataFimCiclo = $dataInicioCiclo->copy()->addDays(30);

                // Verificar se já existe comissão para este ciclo
                $comissaoExistente = AfiliadoComissaoRecorrente::where('afiliado_indicacao_id', $indicacao->id)
                    ->where('assinatura_id', $assinaturaId)
                    ->where('data_inicio_ciclo', $dataInicioCiclo->toDateString())
                    ->first();

                if ($comissaoExistente) {
                    Log::debug('CalcularComissaoRecorrenteUseCase - Comissão já existe para este ciclo', [
                        'comissao_id' => $comissaoExistente->id,
                        'ciclo_inicio' => $dataInicioCiclo->toDateString(),
                    ]);
                    return $comissaoExistente;
                }

                // Calcular comissão baseada no valor EFETIVAMENTE PAGO
                $comissaoPercentual = $indicacao->comissao_percentual ?? 0;
                $valorComissao = ($valorPago * $comissaoPercentual) / 100;

                Log::info('CalcularComissaoRecorrenteUseCase - Calculando comissão', [
                    'afiliado_id' => $indicacao->afiliado_id,
                    'tenant_id' => $tenantId,
                    'empresa_id' => $assinatura->empresa_id,
                    'assinatura_id' => $assinaturaId,
                    'valor_pago' => $valorPago,
                    'comissao_percentual' => $comissaoPercentual,
                    'valor_comissao' => $valorComissao,
                    'ciclo_inicio' => $dataInicioCiclo->toDateString(),
                    'ciclo_fim' => $dataFimCiclo->toDateString(),
                ]);

                // Criar registro de comissão recorrente
                $comissao = AfiliadoComissaoRecorrente::create([
                    'afiliado_id' => $indicacao->afiliado_id,
                    'afiliado_indicacao_id' => $indicacao->id,
                    'tenant_id' => $tenantId,
                    'empresa_id' => $assinatura->empresa_id,
                    'assinatura_id' => $assinaturaId,
                    'data_inicio_ciclo' => $dataInicioCiclo,
                    'data_fim_ciclo' => $dataFimCiclo,
                    'data_pagamento_cliente' => $dataPagamento,
                    'valor_pago_cliente' => $valorPago,
                    'comissao_percentual' => $comissaoPercentual,
                    'valor_comissao' => $valorComissao,
                    'status' => 'pendente',
                ]);

                Log::info('CalcularComissaoRecorrenteUseCase - Comissão recorrente criada', [
                    'comissao_id' => $comissao->id,
                    'valor_comissao' => $valorComissao,
                ]);

                // 🔥 DDD: Disparar Domain Event após comissão gerada
                $event = new ComissaoGerada(
                    comissaoId: $comissao->id,
                    afiliadoId: $indicacao->afiliado_id,
                    tenantId: $tenantId,
                    assinaturaId: $assinaturaId,
                    valor: $valorComissao,
                    tipo: 'recorrente',
                    status: 'pendente',
                    periodoCompetencia: $dataInicioCiclo->format('Y-m') . ' a ' . $dataFimCiclo->format('Y-m'),
                );
                $this->eventDispatcher->dispatch($event);

                return $comissao;

            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        });
    }
}


