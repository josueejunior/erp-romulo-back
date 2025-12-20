<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProcessoController as ApiProcessoController;
use App\Http\Controllers\Api\ProcessoItemController as ApiProcessoItemController;
use App\Http\Controllers\Api\OrcamentoController as ApiOrcamentoController;
use App\Http\Controllers\Api\FormacaoPrecoController as ApiFormacaoPrecoController;
use App\Http\Controllers\Api\DisputaController as ApiDisputaController;
use App\Http\Controllers\Api\JulgamentoController as ApiJulgamentoController;
use App\Http\Controllers\Api\ContratoController as ApiContratoController;
use App\Http\Controllers\Api\AutorizacaoFornecimentoController as ApiAutorizacaoFornecimentoController;
use App\Http\Controllers\Api\EmpenhoController as ApiEmpenhoController;
use App\Http\Controllers\Api\NotaFiscalController as ApiNotaFiscalController;
use App\Http\Controllers\Api\OrgaoController as ApiOrgaoController;
use App\Http\Controllers\Api\SetorController as ApiSetorController;
use App\Http\Controllers\Api\FornecedorController as ApiFornecedorController;
use App\Http\Controllers\Api\CustoIndiretoController as ApiCustoIndiretoController;
use App\Http\Controllers\Api\DocumentoHabilitacaoController as ApiDocumentoHabilitacaoController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\RelatorioFinanceiroController as ApiRelatorioFinanceiroController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\CalendarioDisputasController as ApiCalendarioDisputasController;
use App\Http\Controllers\Api\CalendarioController as ApiCalendarioController;
use App\Http\Controllers\Api\ExportacaoController as ApiExportacaoController;
use App\Http\Controllers\Api\SaldoController as ApiSaldoController;
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
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);

    // Rotas públicas - Planos (podem ser visualizados sem autenticação)
    Route::get('/planos', [ApiPlanoController::class, 'index']);
    Route::get('/planos/{plano}', [ApiPlanoController::class, 'show']);

    // Rotas públicas (autenticação)
    // Rate limiting mais restritivo no login para prevenir brute force
    // 5 tentativas por minuto por IP, bloqueio após 10 tentativas falhas
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:5,1', 'throttle:10,60']); // 5/min, 10/hora
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware(['throttle:3,1', 'throttle:5,60']); // 3/min, 5/hora

    // Rotas autenticadas
    // Rate limiting: 120 requisições por minuto, 1000 por hora
    // Rotas de criação/edição têm rate limiting adicional
    Route::middleware(['auth:sanctum', 'tenancy', 'throttle:120,1'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);
        
        // Dashboard
        Route::get('/dashboard', [ApiDashboardController::class, 'index']);
        
        // Calendário de Disputas (legado)
        Route::get('/calendario/disputas', [ApiCalendarioDisputasController::class, 'index']);
        Route::get('/calendario/eventos', [ApiCalendarioDisputasController::class, 'eventos']);
        
        // Calendário (novo)
        Route::get('/calendario/disputas-novo', [ApiCalendarioController::class, 'disputas']);
        Route::get('/calendario/julgamento', [ApiCalendarioController::class, 'julgamento']);
        Route::get('/calendario/avisos-urgentes', [ApiCalendarioController::class, 'avisosUrgentes']);
        
        // Processos
        Route::get('/processos-resumo', [ApiProcessoController::class, 'resumo']);
        Route::get('/processos/exportar', [ApiProcessoController::class, 'exportar']);
        Route::apiResource('processos', ApiProcessoController::class);
        Route::post('/processos/{processo}/mover-julgamento', [ApiProcessoController::class, 'moverParaJulgamento']);
        Route::post('/processos/{processo}/marcar-vencido', [ApiProcessoController::class, 'marcarVencido']);
        Route::post('/processos/{processo}/marcar-perdido', [ApiProcessoController::class, 'marcarPerdido']);
        Route::get('/processos/{processo}/sugerir-status', [ApiProcessoController::class, 'sugerirStatus']);
        
        // Exportação
        Route::get('/processos/{processo}/exportar/proposta-comercial', [ApiExportacaoController::class, 'propostaComercial']);
        Route::get('/processos/{processo}/exportar/catalogo-ficha-tecnica', [ApiExportacaoController::class, 'catalogoFichaTecnica']);
        
        // Saldo
        Route::get('/processos/{processo}/saldo', [ApiSaldoController::class, 'show']);
        Route::get('/processos/{processo}/saldo-vencido', [ApiSaldoController::class, 'saldoVencido']);
        Route::get('/processos/{processo}/saldo-vinculado', [ApiSaldoController::class, 'saldoVinculado']);
        Route::get('/processos/{processo}/saldo-empenhado', [ApiSaldoController::class, 'saldoEmpenhado']);
        
        // Itens do Processo
        Route::apiResource('processos.itens', ApiProcessoItemController::class)
            ->parameters(['itens' => 'item'])
            ->shallow();
        Route::post('/processos/{processo}/itens/importar', [ApiProcessoItemController::class, 'importar']);
        
        // Orçamentos (por processo - múltiplos itens)
        Route::post('/processos/{processo}/orcamentos', [ApiOrcamentoController::class, 'storeByProcesso']);
        Route::get('/processos/{processo}/orcamentos', [ApiOrcamentoController::class, 'indexByProcesso']);
        Route::put('/processos/{processo}/orcamentos/{orcamento}/itens/{orcamentoItem}', [ApiOrcamentoController::class, 'updateOrcamentoItem']);
        
        // Orçamentos (por item - compatibilidade)
        Route::apiResource('processos.itens.orcamentos', ApiOrcamentoController::class)
            ->parameters([
                'itens' => 'item',
                'orcamentos' => 'orcamento'
            ])
            ->shallow();
        
        // Rota explícita para atualizar orçamento (garantir que PUT funciona)
        Route::put('/processos/{processo}/itens/{item}/orcamentos/{orcamento}', [ApiOrcamentoController::class, 'update'])
            ->name('processos.itens.orcamentos.update');
        
        // Formação de Preços
        Route::apiResource('processos.itens.orcamentos.formacao-preco', ApiFormacaoPrecoController::class)
            ->parameters(['itens' => 'item'])
            ->shallow();
        
        // Disputa
        Route::get('/processos/{processo}/disputa', [ApiDisputaController::class, 'show']);
        Route::put('/processos/{processo}/disputa', [ApiDisputaController::class, 'update']);
        
        // Julgamento
        Route::get('/processos/{processo}/julgamento', [ApiJulgamentoController::class, 'show']);
        Route::put('/processos/{processo}/julgamento', [ApiJulgamentoController::class, 'update']);
        
        // Contratos
        Route::get('/contratos', [ApiContratoController::class, 'listarTodos']); // Lista todos os contratos
        Route::apiResource('processos.contratos', ApiContratoController::class)->shallow();
        
        // Autorizações de Fornecimento
        Route::apiResource('processos.autorizacoes-fornecimento', ApiAutorizacaoFornecimentoController::class)->shallow();
        
        // Empenhos
        Route::apiResource('processos.empenhos', ApiEmpenhoController::class)->shallow();
        
        // Notas Fiscais
        Route::apiResource('processos.notas-fiscais', ApiNotaFiscalController::class)->shallow();
        
        // Cadastros
        Route::apiResource('orgaos', ApiOrgaoController::class);
        Route::apiResource('setors', ApiSetorController::class);
        Route::apiResource('fornecedores', ApiFornecedorController::class)
            ->parameters(['fornecedores' => 'fornecedor']);
        Route::apiResource('custos-indiretos', ApiCustoIndiretoController::class)
            ->parameters(['custos-indiretos' => 'custo-indireto']);
        Route::get('/custos-indiretos-resumo', [ApiCustoIndiretoController::class, 'resumo']);
        Route::apiResource('documentos-habilitacao', ApiDocumentoHabilitacaoController::class);
        
        // Debug/Correção de roles
        Route::get('/user/roles', [\App\Http\Controllers\Api\FixUserRolesController::class, 'getCurrentUserRoles']);
        Route::post('/user/fix-role', [\App\Http\Controllers\Api\FixUserRolesController::class, 'fixCurrentUserRole']);
        
        // Relatórios
        Route::get('/relatorios/financeiro', [ApiRelatorioFinanceiroController::class, 'index']);
        Route::get('/relatorios/gestao-mensal', [ApiRelatorioFinanceiroController::class, 'gestaoMensal']);

        // Usuários
        Route::apiResource('users', ApiUserController::class);

        // Assinaturas (requer autenticação e tenancy)
        // IMPORTANTE: Rotas específicas ANTES de rotas com parâmetros
        Route::get('/assinaturas/atual', [ApiAssinaturaController::class, 'atual']);
        Route::get('/assinaturas/status', [ApiAssinaturaController::class, 'status']);
        Route::get('/assinaturas', [ApiAssinaturaController::class, 'index']);
        Route::post('/assinaturas', [ApiAssinaturaController::class, 'store']);
        Route::post('/assinaturas/{assinatura}/renovar', [ApiAssinaturaController::class, 'renovar']);
        Route::post('/assinaturas/{assinatura}/cancelar', [ApiAssinaturaController::class, 'cancelar']);
    });
});

// Rotas do Painel Admin Central (fora do tenant e fora do v1)
Route::prefix('admin')->group(function () {
    // Autenticação admin - Rate limiting mais restritivo (3/min, 5/hora)
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware(['throttle:3,1', 'throttle:5,60']);
    
    // Rotas protegidas
    Route::middleware(['auth:sanctum', \App\Http\Middleware\IsSuperAdmin::class])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        
        // Gerenciamento de empresas (tenants)
        Route::get('/empresas', [AdminTenantController::class, 'index']);
        Route::get('/empresas/{tenant}', [AdminTenantController::class, 'show']);
        Route::post('/empresas', [AdminTenantController::class, 'store']);
        Route::put('/empresas/{tenant}', [AdminTenantController::class, 'update']);
        Route::delete('/empresas/{tenant}', [AdminTenantController::class, 'destroy']);
        Route::post('/empresas/{tenant}/reativar', [AdminTenantController::class, 'reactivate']);
        
        // Gerenciamento de usuários das empresas
        Route::get('/empresas/{tenant}/usuarios', [AdminUserController::class, 'index']);
        Route::get('/empresas/{tenant}/usuarios/{user}', [AdminUserController::class, 'show']);
        Route::post('/empresas/{tenant}/usuarios', [AdminUserController::class, 'store']);
        Route::put('/empresas/{tenant}/usuarios/{user}', [AdminUserController::class, 'update']);
        Route::delete('/empresas/{tenant}/usuarios/{user}', [AdminUserController::class, 'destroy']);
        Route::post('/empresas/{tenant}/usuarios/{user}/reativar', [AdminUserController::class, 'reactivate']);
        Route::get('/empresas/{tenant}/empresas-disponiveis', [AdminUserController::class, 'empresas']);
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
