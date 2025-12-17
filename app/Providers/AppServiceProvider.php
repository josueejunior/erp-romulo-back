<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use App\Models\Processo;
use App\Models\Contrato;
use App\Models\Empenho;
use App\Models\NotaFiscal;
use App\Observers\ProcessoObserver;
use App\Observers\ContratoObserver;
use App\Observers\EmpenhoObserver;
use App\Observers\NotaFiscalObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar Observers para invalidar cache e atualizar saldos automaticamente
        Processo::observe(ProcessoObserver::class);
        Contrato::observe(ContratoObserver::class);
        Empenho::observe(EmpenhoObserver::class);
        NotaFiscal::observe(NotaFiscalObserver::class);
        
        // Agendar atualização automática de status dos processos
        // Executa a cada hora para verificar processos que passaram da sessão pública
        Schedule::command('processos:atualizar-status')
            ->hourly()
            ->withoutOverlapping();
    }
}
