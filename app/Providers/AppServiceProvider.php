<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;

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
        // Agendar atualização automática de status dos processos
        // Executa a cada hora para verificar processos que passaram da sessão pública
        Schedule::command('processos:atualizar-status')
            ->hourly()
            ->withoutOverlapping();
    }
}
