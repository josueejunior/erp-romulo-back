<?php

namespace App\Modules\Orcamento\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Orcamento\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Orcamento\Repositories\DashboardRepository;
use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
use App\Modules\Orcamento\Domain\Repositories\NotificacaoRepositoryInterface;
use App\Modules\Orcamento\Repositories\NotificacaoRepository;
use App\Modules\Orcamento\Domain\Services\NotificacaoDomainService;
use App\Modules\Orcamento\Domain\Services\ExportacaoDomainService;
use App\Modules\Orcamento\Domain\Services\RelatorioDomainService;

class OrcamentoServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Dashboard
        $this->app->bind(
            DashboardRepositoryInterface::class,
            DashboardRepository::class
        );

        $this->app->singleton(DashboardDomainService::class, function ($app) {
            return new DashboardDomainService(
                $app->make(DashboardRepositoryInterface::class)
            );
        });

        // Notificações
        $this->app->bind(
            NotificacaoRepositoryInterface::class,
            NotificacaoRepository::class
        );

        $this->app->singleton(NotificacaoDomainService::class, function ($app) {
            return new NotificacaoDomainService(
                $app->make(NotificacaoRepositoryInterface::class)
            );
        });

        // Exportação e Relatórios
        $this->app->singleton(ExportacaoDomainService::class, fn() => new ExportacaoDomainService());
        $this->app->singleton(RelatorioDomainService::class, fn() => new RelatorioDomainService());
    }

    public function boot()
    {
        //
    }
}
