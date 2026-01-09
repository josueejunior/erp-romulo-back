<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use App\Modules\Assinatura\Models\Plano;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comando para verificar e processar assinaturas expiradas
 * 
 * Executa diariamente para:
 * - Identificar assinaturas que expiraram
 * - Bloquear acesso após grace period
 * - Tentar cobrança automática (se configurado)
 * - Monitorar planos Trial
 */
class VerificarAssinaturasExpiradas extends Command
{
    protected $signature = 'assinaturas:verificar-expiradas 
                            {--cobrar : Tentar cobrar automaticamente assinaturas expiradas}
                            {--bloquear : Bloquear acesso de assinaturas fora do grace period}';

    protected $description = 'Verifica assinaturas expiradas e processa bloqueios/cobranças';

    public function handle()
    {
        $this->info('🔍 Verificando assinaturas expiradas...');

        $cobrar = $this->option('cobrar');
        $bloquear = $this->option('bloquear');

        // Buscar todos os tenants
        $tenants = Tenant::all();
        $totalProcessados = 0;
        $totalExpiradas = 0;
        $totalBloqueadas = 0;
        $totalCobradas = 0;

        foreach ($tenants as $tenant) {
            try {
                // Inicializar contexto do tenant
                tenancy()->initialize($tenant);

                // Buscar assinaturas ativas ou pendentes (não canceladas)
                $assinaturas = Assinatura::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['ativa', 'pendente', 'suspensa'])
                    ->orderBy('data_fim', 'desc')
                    ->get();
                
                if ($assinaturas->isEmpty()) {
                    $this->warn("  ⚠️  Tenant {$tenant->razao_social} (ID: {$tenant->id}) - Sem assinaturas ativas");
                    tenancy()->end();
                    continue;
                }
                
                // Processar cada assinatura
                foreach ($assinaturas as $assinatura) {
                        $dataFim = Carbon::parse($assinatura->data_fim);
                    $diasExpirado = $hoje->diffInDays($dataFim, false) * -1; // Negativo se expirado

                    // Verificar se expirou
                    if ($diasExpirado > 0) {
                        $totalExpiradas++;

                        // Verificar se está no grace period
                        $diasGracePeriod = $assinatura->dias_grace_period ?? 7;
                        $foraGracePeriod = $diasExpirado > $diasGracePeriod;

                        $planoNome = $assinatura->plano ? ($assinatura->plano->nome ?? 'N/A') : 'N/A';
                        $empresaInfo = $assinatura->empresa_id ? " (Empresa ID: {$assinatura->empresa_id})" : '';
                        
                        $this->line("  📅 Tenant {$tenant->razao_social} (ID: {$tenant->id}){$empresaInfo}");
                        $this->line("     Assinatura ID: {$assinatura->id}");
                        $this->line("     Plano: {$planoNome}");
                        $this->line("     Vencimento: {$dataFim->format('d/m/Y')}");
                        $this->line("     Expirado há: {$diasExpirado} dias");
                        $this->line("     Status atual: {$assinatura->status}");

                        // Se está fora do grace period, suspender/bloquear
                        if ($foraGracePeriod && $bloquear) {
                            // Se ainda está ativa ou pendente, suspender primeiro
                            if (in_array($assinatura->status, ['ativa', 'pendente'])) {
                                $assinatura->update([
                                    'status' => 'suspensa',
                                    'observacoes' => ($assinatura->observacoes ?? '') . "\n⚠️ Suspensa automaticamente por inadimplência em " . $hoje->format('d/m/Y H:i:s') . " (expirado há {$diasExpirado} dias, fora do grace period de {$diasGracePeriod} dias)",
                                ]);
                                $this->warn("     ⚠️  Status alterado para 'suspensa' (inadimplente)");
                                $totalBloqueadas++;
                                
                                Log::warning('Assinatura suspensa por inadimplência', [
                                    'tenant_id' => $tenant->id,
                                    'empresa_id' => $assinatura->empresa_id,
                                    'assinatura_id' => $assinatura->id,
                                    'dias_expirado' => $diasExpirado,
                                    'dias_grace_period' => $diasGracePeriod,
                                ]);
                            } 
                            // Se já está suspensa há mais de 30 dias, marcar como expirada
                            elseif ($assinatura->status === 'suspensa' && $diasExpirado > 30) {
                                $assinatura->update([
                                    'status' => 'expirada',
                                    'observacoes' => ($assinatura->observacoes ?? '') . "\n❌ Expirada automaticamente em " . $hoje->format('d/m/Y H:i:s') . " (suspensa há mais de 30 dias)",
                                ]);
                                $this->error("     ❌ Status alterado para 'expirada' (suspensa há mais de 30 dias)");
                                
                                Log::error('Assinatura expirada após suspensão prolongada', [
                                    'tenant_id' => $tenant->id,
                                    'empresa_id' => $assinatura->empresa_id,
                                    'assinatura_id' => $assinatura->id,
                                    'dias_expirado' => $diasExpirado,
                                ]);
                            }
                        }

                        // Tentar cobrança automática (se configurado e se tem método de pagamento salvo)
                        if ($cobrar && $foraGracePeriod && $assinatura->metodo_pagamento && $assinatura->metodo_pagamento !== 'gratuito') {
                            $this->line("     💳 Tentando cobrança automática...");
                            
                            try {
                                $cobrancaUseCase = app(\App\Application\Assinatura\UseCases\CobrarAssinaturaExpiradaUseCase::class);
                                $resultado = $cobrancaUseCase->executar($tenant->id, $assinatura->id);
                                
                                if ($resultado['sucesso']) {
                                    $this->info("     ✅ Cobrança automática realizada com sucesso!");
                                    $totalCobradas++;
                                } else {
                                    $this->warn("     ⚠️  {$resultado['mensagem']}");
                                }
                            } catch (\Exception $e) {
                                $this->error("     ❌ Erro ao tentar cobrança automática: {$e->getMessage()}");
                                Log::error('Erro ao tentar cobrança automática', [
                                    'tenant_id' => $tenant->id,
                                    'assinatura_id' => $assinatura->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        // Verificar se é Trial e expirou
                        $plano = $assinatura->plano;
                        if ($plano && strtolower($plano->nome) === 'trial') {
                            $this->warn("     ⚠️  Plano Trial expirado - requer ação manual");
                            Log::warning('Plano Trial expirado', [
                                'tenant_id' => $tenant->id,
                                'assinatura_id' => $assinatura->id,
                                'dias_expirado' => $diasExpirado,
                            ]);
                        }
                    } else {
                        // Ainda não expirou
                        $diasRestantes = $diasExpirado * -1;
                        if ($diasRestantes <= 7) {
                            $this->line("  ⚠️  Tenant {$tenant->razao_social} - Assinatura ID {$assinatura->id} vence em {$diasRestantes} dias");
                        }
                    }
                }
                
                $totalProcessados++;
                tenancy()->end();

            } catch (\Exception $e) {
                $this->error("  ❌ Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Erro ao verificar assinatura expirada', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                tenancy()->end();
            }
        }

        $this->info("\n✅ Processamento concluído:");
        $this->info("   Total processados: {$totalProcessados}");
        $this->info("   Total expiradas: {$totalExpiradas}");
        if ($bloquear) {
            $this->info("   Total bloqueadas: {$totalBloqueadas}");
        }
        if ($cobrar) {
            $this->info("   Tentativas de cobrança: {$totalCobradas}");
        }

        return Command::SUCCESS;
    }
}

