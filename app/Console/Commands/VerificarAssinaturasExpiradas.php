<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use App\Modules\Assinatura\Models\Plano;
use App\Application\Afiliado\UseCases\AtualizarComissaoIndicacaoUseCase;
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

    public function __construct(
        private readonly AtualizarComissaoIndicacaoUseCase $atualizarComissaoIndicacaoUseCase,
    ) {
        parent::__construct();
    }

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
        $hoje = Carbon::now();

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
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
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

                                // Atualizar status da indicação de afiliado
                                if ($assinatura->empresa_id) {
                                    try {
                                        $this->atualizarComissaoIndicacaoUseCase->atualizarStatus(
                                            tenantId: $tenant->id,
                                            empresaId: $assinatura->empresa_id,
                                            status: 'expirada'
                                        );
                                    } catch (\Exception $e) {
                                        Log::warning('Erro ao atualizar status de indicação de afiliado', [
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                            }
                        }

                        // 🔥 MELHORIA: Grace Period Ativo - Tentar cobrança automática durante grace period
                        // Não espera expirar completamente, tenta cobrar antes de suspender
                        $estaNoGracePeriod = $diasExpirado > 0 && $diasExpirado <= $diasGracePeriod;
                        
                        if ($cobrar && ($estaNoGracePeriod || $foraGracePeriod) && $assinatura->metodo_pagamento && $assinatura->metodo_pagamento !== 'gratuito') {
                            // Verificar se tem cartão salvo (External Vaulting)
                            if ($assinatura->hasCardToken() && $assinatura->podeTentarCobranca()) {
                                $this->line("     💳 Tentando cobrança automática...");
                                
                                try {
                                    // Processar em background (Fire and Forget)
                                    \App\Jobs\ProcessarCobrancaRecorrente::dispatch($tenant->id, $assinatura->id);
                                    
                                    $this->info("     ✅ Cobrança automática agendada para processamento em background");
                                    $totalCobradas++;
                                    
                                    Log::info('VerificarAssinaturasExpiradas - Cobrança automática agendada', [
                                        'tenant_id' => $tenant->id,
                                        'assinatura_id' => $assinatura->id,
                                        'dias_expirado' => $diasExpirado,
                                        'esta_no_grace_period' => $estaNoGracePeriod,
                                    ]);
                                } catch (\Exception $e) {
                                    $this->error("     ❌ Erro ao agendar cobrança automática: {$e->getMessage()}");
                                    Log::error('Erro ao agendar cobrança automática', [
                                        'tenant_id' => $tenant->id,
                                        'assinatura_id' => $assinatura->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            } elseif (!$assinatura->hasCardToken()) {
                                $this->warn("     ⚠️  Cartão não salvo - cobrança automática não disponível");
                            } elseif (!$assinatura->podeTentarCobranca()) {
                                $this->warn("     ⚠️  Limite de tentativas atingido - aguardando intervalo");
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
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Erro ao processar tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Erro ao verificar assinatura expirada', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
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

