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

                // Buscar assinatura atual
                $assinatura = Assinatura::where('tenant_id', $tenant->id)
                    ->where('status', '!=', 'cancelada')
                    ->orderBy('data_fim', 'desc')
                    ->first();

                if (!$assinatura) {
                    $this->warn("  ⚠️  Tenant {$tenant->razao_social} (ID: {$tenant->id}) - Sem assinatura");
                    tenancy()->end();
                    continue;
                }

                $hoje = Carbon::now();
                $dataFim = Carbon::parse($assinatura->data_fim);
                $diasExpirado = $hoje->diffInDays($dataFim, false) * -1; // Negativo se expirado

                // Verificar se expirou
                if ($diasExpirado > 0) {
                    $totalExpiradas++;

                    // Verificar se está no grace period
                    $diasGracePeriod = $assinatura->dias_grace_period ?? 7;
                    $foraGracePeriod = $diasExpirado > $diasGracePeriod;

                    $this->line("  📅 Tenant {$tenant->razao_social} (ID: {$tenant->id})");
                    $this->line("     Plano: {$assinatura->plano->nome ?? 'N/A'}");
                    $this->line("     Vencimento: {$dataFim->format('d/m/Y')}");
                    $this->line("     Expirado há: {$diasExpirado} dias");

                    // Se está fora do grace period, bloquear
                    if ($foraGracePeriod && $bloquear) {
                        if ($assinatura->status !== 'expirada') {
                            $assinatura->update([
                                'status' => 'expirada',
                                'observacoes' => ($assinatura->observacoes ?? '') . "\nBloqueada automaticamente em " . $hoje->format('d/m/Y H:i:s'),
                            ]);
                            $this->info("     ✅ Status alterado para 'expirada'");
                            $totalBloqueadas++;
                        }
                    }

                    // Tentar cobrança automática (se configurado e se tem método de pagamento salvo)
                    if ($cobrar && $foraGracePeriod && $assinatura->metodo_pagamento && $assinatura->metodo_pagamento !== 'gratuito') {
                        $this->line("     💳 Tentando cobrança automática...");
                        
                        // Aqui você pode implementar lógica de cobrança automática
                        // Por exemplo, tentar renovar usando o último método de pagamento
                        // Por enquanto, apenas logamos
                        Log::info('Tentativa de cobrança automática', [
                            'tenant_id' => $tenant->id,
                            'assinatura_id' => $assinatura->id,
                            'dias_expirado' => $diasExpirado,
                        ]);
                        
                        $this->warn("     ⚠️  Cobrança automática não implementada ainda");
                        // TODO: Implementar cobrança automática
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
                        $this->line("  ⚠️  Tenant {$tenant->razao_social} - Vencimento em {$diasRestantes} dias");
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

