<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use App\Models\Processo;
use App\Observers\ProcessoObserver;

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
        // Registrar Observer para invalidar cache automaticamente
        Processo::observe(ProcessoObserver::class);
        
        // Agendar atualização automática de status dos processos
        // Executa a cada hora para verificar processos que passaram da sessão pública
        Schedule::command('processos:atualizar-status')
            ->hourly()
            ->withoutOverlapping();
    }
}
