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

        // DDD: Bindings de Interfaces para Implementações
        // Domain -> Infrastructure
        $this->app->bind(
            \App\Domain\Tenant\Repositories\TenantRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\TenantRepository::class
        );

        $this->app->bind(
            \App\Domain\Tenant\Services\TenantDatabaseServiceInterface::class,
            \App\Infrastructure\Tenant\TenantDatabaseService::class
        );

        $this->app->bind(
            \App\Domain\Tenant\Services\TenantRolesServiceInterface::class,
            \App\Infrastructure\Tenant\TenantRolesService::class
        );

        $this->app->bind(
            \App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\EmpresaRepository::class
        );

        $this->app->bind(
            \App\Domain\Auth\Repositories\UserRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\UserRepository::class
        );

        $this->app->bind(
            \App\Domain\Processo\Repositories\ProcessoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\ProcessoRepository::class
        );

        $this->app->bind(
            \App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\FornecedorRepository::class
        );

        $this->app->bind(
            \App\Domain\Contrato\Repositories\ContratoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\ContratoRepository::class
        );

        $this->app->bind(
            \App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\EmpenhoRepository::class
        );

        $this->app->bind(
            \App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\NotaFiscalRepository::class
        );

        $this->app->bind(
            \App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\OrcamentoRepository::class
        );

        $this->app->bind(
            \App\Domain\Orgao\Repositories\OrgaoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\OrgaoRepository::class
        );

        $this->app->bind(
            \App\Domain\Setor\Repositories\SetorRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\SetorRepository::class
        );

        $this->app->bind(
            \App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\AutorizacaoFornecimentoRepository::class
        );

        $this->app->bind(
            \App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\DocumentoHabilitacaoRepository::class
        );

        $this->app->bind(
            \App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\CustoIndiretoRepository::class
        );

        $this->app->bind(
            \App\Domain\FormacaoPreco\Repositories\FormacaoPrecoRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\FormacaoPrecoRepository::class
        );

        $this->app->bind(
            \App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\ProcessoItemRepository::class
        );

        $this->app->bind(
            \App\Domain\OrcamentoItem\Repositories\OrcamentoItemRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\OrcamentoItemRepository::class
        );

        // Domain Services
        $this->app->bind(
            \App\Domain\Auth\Services\UserRoleServiceInterface::class,
            \App\Infrastructure\Auth\UserRoleService::class
        );

        // Event Dispatcher
        $this->app->bind(
            \App\Domain\Shared\Events\EventDispatcherInterface::class,
            \App\Infrastructure\Events\LaravelEventDispatcher::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        // Registrar Listeners para Domain Events
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Auth\Events\UsuarioCriado::class,
            \App\Listeners\UsuarioCriadoListener::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Auth\Events\SenhaAlterada::class,
            \App\Listeners\SenhaAlteradaListener::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Tenant\Events\EmpresaVinculada::class,
            \App\Listeners\EmpresaVinculadaListener::class
        );
    }
}
