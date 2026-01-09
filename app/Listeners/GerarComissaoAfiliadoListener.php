<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Assinatura\Events\AssinaturaCriada;
use App\Domain\Assinatura\Events\AssinaturaAtualizada;
use App\Application\Afiliado\UseCases\CalcularComissaoRecorrenteUseCase;
use App\Application\Afiliado\UseCases\CriarIndicacaoAfiliadoUseCase;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura as AssinaturaModel;
use App\Modules\Assinatura\Models\Plano;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;
use Carbon\Carbon;

/**
 * Listener para eventos de Assinatura
 * Gera comissão de afiliado quando pagamento é confirmado
 */
class GerarComissaoAfiliadoListener
{
    public function __construct(
        private readonly CalcularComissaoRecorrenteUseCase $calcularComissaoRecorrenteUseCase,
        private readonly CriarIndicacaoAfiliadoUseCase $criarIndicacaoAfiliadoUseCase,
    ) {}

    /**
     * Handle AssinaturaCriada event
     * Cria indicação inicial se houver afiliado
     */
    public function handleAssinaturaCriada(AssinaturaCriada $event): void
    {
        $this->processarComissao($event, isNovaAssinatura: true);
    }

    /**
     * Handle AssinaturaAtualizada event
     * Gera comissão recorrente quando pagamento é confirmado
     */
    public function handleAssinaturaAtualizada(AssinaturaAtualizada $event): void
    {
        $this->processarComissao($event, isNovaAssinatura: false);
    }

    /**
     * Processa comissão de afiliado
     */
    private function processarComissao(AssinaturaCriada|AssinaturaAtualizada $event, bool $isNovaAssinatura): void
    {
        Log::info('GerarComissaoAfiliadoListener - Processando comissão', [
            'assinatura_id' => $event->assinaturaId,
            'tenant_id' => $event->tenantId,
            'empresa_id' => $event->empresaId,
            'status' => $event->status,
            'is_nova' => $isNovaAssinatura,
        ]);

        // Só processar se assinatura está ativa e pagamento foi confirmado
        if ($event->status !== 'ativa') {
            Log::debug('GerarComissaoAfiliadoListener - Assinatura não está ativa, ignorando', [
                'status' => $event->status,
            ]);
            return;
        }

        try {
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                Log::warning('GerarComissaoAfiliadoListener - Tenant não encontrado', [
                    'tenant_id' => $event->tenantId,
                ]);
                return;
            }

            Tenancy::initialize($tenant);

            try {
                // Buscar assinatura
                $assinatura = AssinaturaModel::find($event->assinaturaId);
                if (!$assinatura) {
                    Log::warning('GerarComissaoAfiliadoListener - Assinatura não encontrada', [
                        'assinatura_id' => $event->assinaturaId,
                    ]);
                    return;
                }

                // Buscar empresa para verificar se tem afiliado
                $empresa = Empresa::find($event->empresaId);
                if (!$empresa || !$empresa->afiliado_id) {
                    Log::debug('GerarComissaoAfiliadoListener - Empresa não tem afiliado vinculado', [
                        'empresa_id' => $event->empresaId,
                    ]);
                    return;
                }

                // Buscar plano
                $plano = Plano::find($event->planoId);
                if (!$plano) {
                    Log::warning('GerarComissaoAfiliadoListener - Plano não encontrado', [
                        'plano_id' => $event->planoId,
                    ]);
                    return;
                }

                // Se é nova assinatura e tem afiliado, criar indicação inicial
                if ($isNovaAssinatura) {
                    $this->criarIndicacaoInicial($event, $empresa, $plano, $assinatura);
                }

                // Se pagamento foi confirmado (assinatura ativa com valor pago), gerar comissão recorrente
                if ($assinatura->valor_pago > 0 && $assinatura->transacao_id) {
                    $this->calcularComissaoRecorrente($event, $assinatura);
                }

            } finally {
                if (Tenancy::initialized()) {
                    Tenancy::end();
                }
            }

        } catch (\Exception $e) {
            Log::error('GerarComissaoAfiliadoListener - Erro ao processar comissão', [
                'assinatura_id' => $event->assinaturaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Cria indicação inicial de afiliado
     */
    private function criarIndicacaoInicial($event, $empresa, $plano, $assinatura): void
    {
        try {
            $valorOriginal = $plano->preco_mensal ?? 0;
            $valorComDesconto = $assinatura->valor_pago ?? $valorOriginal;
            $descontoAplicado = $empresa->afiliado_desconto_aplicado ?? 0;

            $this->criarIndicacaoAfiliadoUseCase->executar(
                afiliadoId: $empresa->afiliado_id,
                tenantId: $event->tenantId,
                empresaId: $event->empresaId,
                codigoUsado: $empresa->afiliado_codigo ?? '',
                descontoAplicado: $descontoAplicado,
                planoId: $plano->id,
                valorPlanoOriginal: $valorOriginal,
                valorPlanoComDesconto: $valorComDesconto
            );

            Log::info('GerarComissaoAfiliadoListener - Indicação inicial criada', [
                'afiliado_id' => $empresa->afiliado_id,
                'tenant_id' => $event->tenantId,
            ]);
        } catch (\Exception $e) {
            Log::error('GerarComissaoAfiliadoListener - Erro ao criar indicação inicial', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calcula comissão recorrente
     */
    private function calcularComissaoRecorrente($event, $assinatura): void
    {
        try {
            $valorPago = $assinatura->valor_pago ?? 0;
            $dataPagamento = $assinatura->data_inicio ?? Carbon::now();

            $this->calcularComissaoRecorrenteUseCase->executar(
                tenantId: $event->tenantId,
                assinaturaId: $event->assinaturaId,
                valorPago: $valorPago,
                dataPagamento: $dataPagamento
            );

            Log::info('GerarComissaoAfiliadoListener - Comissão recorrente calculada', [
                'assinatura_id' => $event->assinaturaId,
                'valor_pago' => $valorPago,
            ]);
        } catch (\Exception $e) {
            Log::error('GerarComissaoAfiliadoListener - Erro ao calcular comissão recorrente', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

