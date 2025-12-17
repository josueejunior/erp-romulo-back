<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use App\Models\Processo;
use App\Models\Contrato;
use App\Models\Empenho;
use App\Models\NotaFiscal;
use App\Models\Orcamento;
use App\Models\AutorizacaoFornecimento;
use App\Observers\ProcessoObserver;
use App\Observers\ContratoObserver;
use App\Observers\EmpenhoObserver;
use App\Observers\NotaFiscalObserver;
use App\Observers\AuditObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar Policies
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Processo::class, \App\Policies\ProcessoPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Contrato::class, \App\Policies\ContratoPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Orcamento::class, \App\Policies\OrcamentoPolicy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar Observers para invalidar cache e atualizar saldos automaticamente
        Processo::observe([ProcessoObserver::class, AuditObserver::class]);
        Contrato::observe([ContratoObserver::class, AuditObserver::class]);
        Empenho::observe([EmpenhoObserver::class, AuditObserver::class]);
        NotaFiscal::observe([NotaFiscalObserver::class, AuditObserver::class]);
        Orcamento::observe(AuditObserver::class);
        AutorizacaoFornecimento::observe(AuditObserver::class);
        
        // Agendar atualização automática de status dos processos
        // Executa a cada hora para verificar processos que passaram da sessão pública
        Schedule::command('processos:atualizar-status')
            ->hourly()
            ->withoutOverlapping();
    }
}
