<?php

namespace App\Console\Commands;

use App\Modules\Contrato\Models\Contrato;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Tenant;

class AtualizarVigenciaContratosAFs extends Command
{
    protected $signature = 'contratos:atualizar-vigencia';
    protected $description = 'Atualiza vigência de contratos e AFs expirados';

    public function handle(): int
    {
        try {
            $this->info('Iniciando atualização de vigência de contratos e AFs...');
            
            $totalContratosAtualizados = 0;
            $totalAFsAtualizados = 0;
            $hoje = Carbon::now();

            // Buscar todos os tenants
            $tenants = Tenant::all();

            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);
                    
                    $this->line("Processando tenant: {$tenant->id} - {$tenant->razao_social}");

                    // Atualizar contratos expirados
                    $contratosExpirados = Contrato::where('vigente', true)
                        ->whereNotNull('data_fim')
                        ->where('data_fim', '<=', $hoje)
                        ->get();

                    foreach ($contratosExpirados as $contrato) {
                        $contrato->vigente = false;
                        $contrato->save();
                        
                        $totalContratosAtualizados++;
                        
                        Log::info('Contrato expirado marcado como não vigente', [
                            'contrato_id' => $contrato->id,
                            'processo_id' => $contrato->processo_id,
                            'data_fim' => $contrato->data_fim->format('Y-m-d'),
                        ]);
                    }

                    // Atualizar AFs expiradas
                    $afsExpiradas = AutorizacaoFornecimento::where('vigente', true)
                        ->whereNotNull('data_fim_vigencia')
                        ->where('data_fim_vigencia', '<=', $hoje)
                        ->get();

                    foreach ($afsExpiradas as $af) {
                        $af->vigente = false;
                        $af->save();
                        
                        // Atualizar situação também
                        $af->atualizarSaldo();
                        
                        $totalAFsAtualizados++;
                        
                        Log::info('AF expirada marcada como não vigente', [
                            'af_id' => $af->id,
                            'processo_id' => $af->processo_id,
                            'data_fim_vigencia' => $af->data_fim_vigencia->format('Y-m-d'),
                        ]);
                    }

                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao processar tenant no comando de atualização de vigência', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error("  Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }

            $this->info("Total de contratos atualizados: {$totalContratosAtualizados}");
            $this->info("Total de AFs atualizadas: {$totalAFsAtualizados}");
            $this->info('Atualização de vigência concluída!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('contratos:atualizar-vigencia erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}

