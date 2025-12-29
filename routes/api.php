<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Modules\Processo\Controllers\ProcessoController as ApiProcessoController;
use App\Modules\Processo\Controllers\ProcessoItemController as ApiProcessoItemController;
use App\Modules\Orcamento\Controllers\OrcamentoController as ApiOrcamentoController;
use App\Modules\Orcamento\Controllers\FormacaoPrecoController as ApiFormacaoPrecoController;
use App\Modules\Processo\Controllers\DisputaController as ApiDisputaController;
use App\Modules\Processo\Controllers\JulgamentoController as ApiJulgamentoController;
use App\Modules\Contrato\Controllers\ContratoController as ApiContratoController;
use App\Modules\AutorizacaoFornecimento\Controllers\AutorizacaoFornecimentoController as ApiAutorizacaoFornecimentoController;
use App\Modules\Empenho\Controllers\EmpenhoController as ApiEmpenhoController;
use App\Modules\NotaFiscal\Controllers\NotaFiscalController as ApiNotaFiscalController;
use App\Modules\Orgao\Controllers\OrgaoController as ApiOrgaoController;
use App\Modules\Orgao\Controllers\SetorController as ApiSetorController;
use App\Modules\Fornecedor\Controllers\FornecedorController as ApiFornecedorController;
use App\Modules\Custo\Controllers\CustoIndiretoController as ApiCustoIndiretoController;
use App\Modules\Documento\Controllers\DocumentoHabilitacaoController as ApiDocumentoHabilitacaoController;
use App\Modules\Dashboard\Controllers\DashboardController as ApiDashboardController;
use App\Modules\Relatorio\Controllers\RelatorioFinanceiroController as ApiRelatorioFinanceiroController;
use App\Http\Controllers\Api\TenantController;
use App\Modules\Calendario\Controllers\CalendarioDisputasController as ApiCalendarioDisputasController;
use App\Modules\Calendario\Controllers\CalendarioController as ApiCalendarioController;
use App\Modules\Processo\Controllers\ExportacaoController as ApiExportacaoController;
use App\Modules\Processo\Controllers\SaldoController as ApiSaldoController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\PlanoController as ApiPlanoController;
use App\Http\Controllers\Api\AssinaturaController as ApiAssinaturaController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminUserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Rotas públicas (central) - Gerenciamento de Tenants/Empresas
    // Rate limiting mais permissivo para criação de tenants (10/min, 20/hora)
    Route::module('tenants', TenantController::class, 'tenant')
        ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
        ->middleware(['throttle:10,1', 'throttle:20,60']);

    // Rotas públicas - Planos (podem ser visualizados sem autenticação)
    Route::module('planos', ApiPlanoController::class, 'plano')
        ->only(['list', 'get']);

    // Rotas públicas (autenticação)
    // Rate limiting: aumentado para desenvolvimento/testes
    // Em produção, considere reduzir para prevenir brute force
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:20,1', 'throttle:50,60']); // 20/min, 50/hora
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware(['throttle:10,1', 'throttle:20,60']); // 10/min, 20/hora

    // Rotas autenticadas
    // Rate limiting: 120 requisições por minuto, 1000 por hora
    // Rotas de criação/edição têm rate limiting adicional
    Route::middleware(['auth:sanctum', \App\Http\Middleware\SetAuthContext::class, 'tenancy', 'throttle:120,1'])->group(function () {
        // Autenticação
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);
        
        // Rota de compatibilidade para /user/roles (redireciona para /users/roles)
        Route::get('/user/roles', [\App\Http\Controllers\Api\FixUserRolesController::class, 'getCurrentUserRoles']);
        
        // Dashboard
        Route::get('/dashboard', [ApiDashboardController::class, 'index']);
        
        // Calendário
        Route::prefix('calendario')->group(function () {
            // Calendário de Disputas (legado)
            Route::get('/disputas', [ApiCalendarioDisputasController::class, 'index']);
            Route::get('/eventos', [ApiCalendarioDisputasController::class, 'eventos']);
            
            // Calendário (novo)
            Route::get('/disputas-novo', [ApiCalendarioController::class, 'disputas']);
            Route::get('/julgamento', [ApiCalendarioController::class, 'julgamento']);
            Route::get('/avisos-urgentes', [ApiCalendarioController::class, 'avisosUrgentes']);
        });
        
        // Processos
        Route::prefix('processos')->group(function () {
            // Rotas customizadas (fora do Route::module)
            Route::get('/resumo', [ApiProcessoController::class, 'resumo']);
            Route::get('/exportar', [ApiProcessoController::class, 'exportar']);
        });
        
        Route::module('processos', ApiProcessoController::class, 'processo')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
            ->children(function () {
                // Rotas customizadas de processo
                Route::post('/mover-julgamento', [ApiProcessoController::class, 'moverParaJulgamento']);
                Route::post('/marcar-vencido', [ApiProcessoController::class, 'marcarVencido']);
                Route::post('/marcar-perdido', [ApiProcessoController::class, 'marcarPerdido']);
                Route::get('/sugerir-status', [ApiProcessoController::class, 'sugerirStatus']);
                
                // Exportação
                Route::get('/exportar/proposta-comercial', [ApiExportacaoController::class, 'propostaComercial']);
                Route::get('/exportar/catalogo-ficha-tecnica', [ApiExportacaoController::class, 'catalogoFichaTecnica']);
                
                // Saldo
                Route::get('/saldo', [ApiSaldoController::class, 'show']);
                Route::get('/saldo-vencido', [ApiSaldoController::class, 'saldoVencido']);
                Route::get('/saldo-vinculado', [ApiSaldoController::class, 'saldoVinculado']);
                Route::get('/saldo-empenhado', [ApiSaldoController::class, 'saldoEmpenhado']);
                
                // Disputa
                Route::get('/disputa', [ApiDisputaController::class, 'show']);
                Route::put('/disputa', [ApiDisputaController::class, 'update']);
                
                // Julgamento
                Route::get('/julgamento', [ApiJulgamentoController::class, 'show']);
                Route::put('/julgamento', [ApiJulgamentoController::class, 'update']);
                
                // Itens do Processo
                Route::module('itens', ApiProcessoItemController::class, 'item')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
                    ->children(function () {
                        // Orçamentos (por item - compatibilidade)
                        Route::module('orcamentos', ApiOrcamentoController::class, 'orcamento')
                            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
                            ->children(function () {
                                // Formação de Preços
                                Route::module('formacao-preco', ApiFormacaoPrecoController::class, 'formacaoPreco')
                                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
                            });
                    });
                Route::post('/itens/importar', [ApiProcessoItemController::class, 'importar']);
                
                // Orçamentos (por processo - múltiplos itens)
                Route::post('/orcamentos', [ApiOrcamentoController::class, 'storeByProcesso']);
                Route::get('/orcamentos', [ApiOrcamentoController::class, 'indexByProcesso']);
                Route::put('/orcamentos/{orcamento}/itens/{orcamentoItem}', [ApiOrcamentoController::class, 'updateOrcamentoItem']);
                
                // Contratos
                Route::module('contratos', ApiContratoController::class, 'contrato')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
                
                // Autorizações de Fornecimento
                Route::module('autorizacoes-fornecimento', ApiAutorizacaoFornecimentoController::class, 'autorizacaoFornecimento')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
                
                // Empenhos
                Route::module('empenhos', ApiEmpenhoController::class, 'empenho')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
                
                // Notas Fiscais
                Route::module('notas-fiscais', ApiNotaFiscalController::class, 'notaFiscal')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
            });
        
        // Contratos
        Route::prefix('contratos')->group(function () {
            Route::get('/', [ApiContratoController::class, 'listarTodos']); // Lista todos os contratos
        });
        
        // Cadastros
        Route::module('orgaos', ApiOrgaoController::class, 'orgao')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
        
        Route::module('setors', ApiSetorController::class, 'setor')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
        
        Route::module('fornecedores', ApiFornecedorController::class, 'fornecedor')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
        
        Route::module('custos-indiretos', ApiCustoIndiretoController::class, 'id')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
            ->group(function () {
                Route::get('/resumo', [ApiCustoIndiretoController::class, 'resumo']);
            });
        
        // Rota de compatibilidade para /custos-indiretos-resumo
        Route::get('/custos-indiretos-resumo', [ApiCustoIndiretoController::class, 'resumo']);
        
        Route::module('documentos-habilitacao', ApiDocumentoHabilitacaoController::class, 'documentoHabilitacao')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
        
        // Usuários
        Route::module('users', ApiUserController::class, 'user')
            ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
            ->group(function () {
                // Debug/Correção de roles
                Route::get('/roles', [\App\Http\Controllers\Api\FixUserRolesController::class, 'getCurrentUserRoles']);
                Route::post('/fix-role', [\App\Http\Controllers\Api\FixUserRolesController::class, 'fixCurrentUserRole']);
                // Trocar empresa ativa
                Route::put('/empresa-ativa', [ApiUserController::class, 'switchEmpresaAtiva']);
            });
        
        // Relatórios
        Route::prefix('relatorios')->group(function () {
            Route::get('/financeiro', [ApiRelatorioFinanceiroController::class, 'index']);
            Route::get('/gestao-mensal', [ApiRelatorioFinanceiroController::class, 'gestaoMensal']);
        });

        // Assinaturas (requer autenticação e tenancy)
        Route::prefix('assinaturas')->group(function () {
            // Rotas específicas ANTES de rotas com parâmetros
            Route::get('/atual', [ApiAssinaturaController::class, 'atual']);
            Route::get('/status', [ApiAssinaturaController::class, 'status']);
            Route::get('/', [ApiAssinaturaController::class, 'index']);
            Route::post('/', [ApiAssinaturaController::class, 'store']);
            Route::post('/{assinatura}/renovar', [ApiAssinaturaController::class, 'renovar']);
            Route::post('/{assinatura}/cancelar', [ApiAssinaturaController::class, 'cancelar']);
        });
    });
});

// Rotas do Painel Admin Central (fora do tenant e fora do v1)
Route::prefix('admin')->group(function () {
    // Autenticação admin - Rate limiting mais restritivo (3/min, 5/hora)
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware(['throttle:3,1', 'throttle:5,60']);
    
    // Rotas protegidas - usar middleware 'admin' que valida no backend
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Autenticação admin
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        
        // Rotas globais para usuários (sem necessidade de especificar tenant)
        Route::prefix('usuarios')->group(function () {
            Route::get('/', [AdminUserController::class, 'indexGlobal']);
            Route::get('/{userId}', [AdminUserController::class, 'showGlobal'])->where('userId', '[0-9]+');
        });
        
        // Gerenciamento de empresas (tenants)
        Route::prefix('empresas')->group(function () {
            Route::get('/', [AdminTenantController::class, 'index']);
            Route::get('/{tenant}', [AdminTenantController::class, 'show']);
            Route::post('/', [AdminTenantController::class, 'store']);
            Route::put('/{tenant}', [AdminTenantController::class, 'update']);
            Route::delete('/{tenant}', [AdminTenantController::class, 'destroy']);
            Route::post('/{tenant}/reativar', [AdminTenantController::class, 'reactivate']);
            
            // Gerenciamento de usuários das empresas
            // Middleware InitializeTenant cuida do contexto do tenant automaticamente
            Route::prefix('{tenant}/usuarios')
                ->middleware([\App\Http\Middleware\InitializeTenant::class])
                ->group(function () {
                    Route::get('/', [AdminUserController::class, 'index']);
                    Route::get('/{userId}', [AdminUserController::class, 'show'])->where('userId', '[0-9]+');
                    Route::post('/', [AdminUserController::class, 'store']);
                    Route::put('/{userId}', [AdminUserController::class, 'update'])->where('userId', '[0-9]+');
                    Route::delete('/{userId}', [AdminUserController::class, 'destroy'])->where('userId', '[0-9]+');
                    Route::post('/{userId}/reativar', [AdminUserController::class, 'reactivate'])->where('userId', '[0-9]+');
                });
            
            // Empresas disponíveis para usuário
            Route::get('/{tenant}/empresas-disponiveis', [AdminUserController::class, 'empresas'])
                ->middleware([\App\Http\Middleware\InitializeTenant::class]);
        });
    });
});

// Fallback para 404 em JSON
Route::fallback(function (Request $request) {
    \Log::warning('Rota não encontrada (fallback)', [
        'url' => $request->url(),
        'path' => $request->path(),
        'method' => $request->method(),
        'headers' => $request->headers->all()
    ]);
    return response()->json([
        'message' => 'Rota não encontrada.',
        'path' => $request->path(),
        'method' => $request->method()
    ], 404);
});
