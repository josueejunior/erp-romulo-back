<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agenda checagem diária de documentos de habilitação vencendo/vencidos
Schedule::command('documentos:vencimento')->dailyAt('06:00');
// Cleanup diário de uploads de documentos de processos não referenciados
Schedule::command('documentos:cleanup-processos')->dailyAt('03:30');
