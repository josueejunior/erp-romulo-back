<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Application\Afiliado\UseCases\CalcularComissaoRecorrenteUseCase;
use App\Modules\Afiliado\Models\AfiliadoIndicacao;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Command para calcular comissões recorrentes de afiliados
 * 
 * Roda diariamente e calcula comissões para assinaturas ativas
 * que tiveram pagamento confirmado no ciclo atual
 */
class CalcularComissoesRecorrentes extends Command
{
    protected $signature = 'afiliados:calcular-comissoes {--bloquear : Bloquear execução simultânea}';
    protected $description = 'Calcula comissões recorrentes de afiliados para assinaturas ativas';

    public function __construct(
        private readonly CalcularComissaoRecorrenteUseCase $calcularComissaoRecorrenteUseCase,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando cálculo de comissões recorrentes...');

        $bloquear = $this->option('bloquear');
        if ($bloquear) {
            $lockFile = storage_path('app/comissoes_calculando.lock');
            if (file_exists($lockFile)) {
                $this->warn('Comando já está em execução. Pulando...');
                return 0;
            }
            touch($lockFile);
        }

        try {
            $hoje = Carbon::now()->startOfDay();
            
            // Buscar todas as indicações ativas
            $indicacoes = AfiliadoIndicacao::where('status', 'ativa')
                ->whereNotNull('primeira_assinatura_em')
                ->get();

            $this->info("Encontradas {$indicacoes->count()} indicações ativas");

            $comissoesGeradas = 0;
            $erros = 0;

            foreach ($indicacoes as $indicacao) {
                try {
                    $tenant = Tenant::find($indicacao->tenant_id);
                    if (!$tenant) {
                        $this->warn("Tenant {$indicacao->tenant_id} não encontrado para indicação {$indicacao->id}");
                        continue;
                    }

                    tenancy()->initialize($tenant);

                    try {
                        // Buscar assinatura ativa da empresa
                        $assinatura = Assinatura::where('empresa_id', $indicacao->empresa_id)
                            ->where('status', 'ativa')
                            ->where('valor_pago', '>', 0)
                            ->whereNotNull('transacao_id')
                            ->orderBy('data_inicio', 'desc')
                            ->first();

                        if (!$assinatura) {
                            continue;
                        }

                        // Verificar se já passou um ciclo de 30 dias desde a última comissão
                        $ultimaComissao = \App\Models\AfiliadoComissaoRecorrente::where('afiliado_indicacao_id', $indicacao->id)
                            ->where('status', 'pendente')
                            ->orderBy('data_inicio_ciclo', 'desc')
                            ->first();

                        if ($ultimaComissao) {
                            $proximoCiclo = Carbon::parse($ultimaComissao->data_fim_ciclo)->addDay();
                            if ($hoje->lt($proximoCiclo)) {
                                // Ainda não completou o ciclo
                                continue;
                            }
                        }

                        // Calcular comissão para o ciclo atual
                        $dataInicioCiclo = $ultimaComissao 
                            ? Carbon::parse($ultimaComissao->data_fim_ciclo)->addDay()
                            : Carbon::parse($assinatura->data_inicio);

                        $dataFimCiclo = $dataInicioCiclo->copy()->addDays(30);

                        // Verificar se já existe comissão para este ciclo
                        $comissaoExistente = \App\Models\AfiliadoComissaoRecorrente::where('afiliado_indicacao_id', $indicacao->id)
                            ->where('assinatura_id', $assinatura->id)
                            ->where('data_inicio_ciclo', $dataInicioCiclo->toDateString())
                            ->first();

                        if ($comissaoExistente) {
                            continue;
                        }

                        // Gerar comissão
                        $comissao = $this->calcularComissaoRecorrenteUseCase->executar(
                            tenantId: $indicacao->tenant_id,
                            assinaturaId: $assinatura->id,
                            valorPago: $assinatura->valor_pago,
                            dataPagamento: $assinatura->data_inicio
                        );

                        if ($comissao) {
                            $comissoesGeradas++;
                            $this->info("Comissão gerada: Afiliado {$indicacao->afiliado_id}, Valor: R$ {$comissao->valor_comissao}");
                        }

                    } finally {
                        if (tenancy()->initialized) {
                            tenancy()->end();
                        }
                    }

                } catch (\Exception $e) {
                    $erros++;
                    $this->error("Erro ao processar indicação {$indicacao->id}: {$e->getMessage()}");
                    Log::error('CalcularComissoesRecorrentes - Erro', [
                        'indicacao_id' => $indicacao->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Comissões geradas: {$comissoesGeradas}");
            if ($erros > 0) {
                $this->warn("Erros encontrados: {$erros}");
            }

            return 0;

        } finally {
            if ($bloquear && isset($lockFile) && file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }
}




