<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use App\Database\Schema\Blueprint;
use App\Http\Routing\ModuleRegistrar;
use App\Modules\Processo\Models\Processo;
use App\Models\Contrato;
use App\Models\Empenho;
use App\Models\NotaFiscal;
use App\Models\Orcamento;
use App\Models\AutorizacaoFornecimento;
use App\Modules\Processo\Observers\ProcessoObserver;
use App\Observers\ContratoObserver;
use App\Observers\EmpenhoObserver;
use App\Observers\NotaFiscalObserver;
use App\Observers\AuditObserver;
use Laravel\Sanctum\Sanctum;
use App\Modules\Auth\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar modelo customizado do Sanctum para usar timestamps em português
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        
        // Registrar Policies
        \Illuminate\Support\Facades\Gate::policy(\App\Modules\Processo\Models\Processo::class, \App\Modules\Processo\Policies\ProcessoPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Contrato::class, \App\Policies\ContratoPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Orcamento::class, \App\Policies\OrcamentoPolicy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mapear relacionamentos polimórficos para manter compatibilidade com dados antigos
        // O evento 'retrieved' no PersonalAccessToken faz o mapeamento de namespaces antigos
        // Aqui definimos os novos namespaces como padrão para novos tokens
        Relation::morphMap([
            'admin_user' => \App\Modules\Auth\Models\AdminUser::class,
            'user' => \App\Modules\Auth\Models\User::class,
        ]);
        
        // Registrar macro Route::module
        Route::macro('module', function (string $prefix, string $controller, string $parameter): ModuleRegistrar {
            return new ModuleRegistrar(app('router'), $prefix, $controller, $parameter);
        });
        
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
