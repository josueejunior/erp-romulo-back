<?php

namespace App\Modules\Orcamento\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Orcamento\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Orcamento\Repositories\DashboardRepository;
use App\Modules\Orcamento\Domain\Services\DashboardDomainService;

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
    }

    public function boot()
    {
        //
    }
}
