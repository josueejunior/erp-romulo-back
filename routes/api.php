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
use App\Http\Controllers\Api\FornecedorController as ApiFornecedorController;
use App\Http\Controllers\Api\DocumentoHabilitacaoController as ApiDocumentoHabilitacaoController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\RelatorioFinanceiroController as ApiRelatorioFinanceiroController;

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

// Rotas públicas (central)
Route::post('/tenants', [\App\Http\Controllers\Api\TenantController::class, 'store']);
Route::get('/tenants', [\App\Http\Controllers\Api\TenantController::class, 'index']);
Route::get('/tenants/{tenant}', [\App\Http\Controllers\Api\TenantController::class, 'show']);

// Rotas públicas (autenticação)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Rotas autenticadas
Route::middleware(['auth:sanctum', 'tenancy'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Dashboard
    Route::get('/dashboard', [ApiDashboardController::class, 'index']);
    
    // Processos
    Route::apiResource('processos', ApiProcessoController::class);
    Route::post('/processos/{processo}/marcar-vencido', [ApiProcessoController::class, 'marcarVencido']);
    Route::post('/processos/{processo}/marcar-perdido', [ApiProcessoController::class, 'marcarPerdido']);
    
    // Itens do Processo
    Route::apiResource('processos.itens', ApiProcessoItemController::class)->shallow();
    
    // Orçamentos
    Route::apiResource('processos.itens.orcamentos', ApiOrcamentoController::class)->shallow();
    
    // Formação de Preços
    Route::apiResource('processos.itens.orcamentos.formacao-preco', ApiFormacaoPrecoController::class)->shallow();
    
    // Disputa
    Route::get('/processos/{processo}/disputa', [ApiDisputaController::class, 'show']);
    Route::put('/processos/{processo}/disputa', [ApiDisputaController::class, 'update']);
    
    // Julgamento
    Route::get('/processos/{processo}/julgamento', [ApiJulgamentoController::class, 'show']);
    Route::put('/processos/{processo}/julgamento', [ApiJulgamentoController::class, 'update']);
    
    // Contratos
    Route::apiResource('processos.contratos', ApiContratoController::class)->shallow();
    
    // Autorizações de Fornecimento
    Route::apiResource('processos.autorizacoes-fornecimento', ApiAutorizacaoFornecimentoController::class)->shallow();
    
    // Empenhos
    Route::apiResource('processos.empenhos', ApiEmpenhoController::class)->shallow();
    
    // Notas Fiscais
    Route::apiResource('processos.notas-fiscais', ApiNotaFiscalController::class)->shallow();
    
    // Cadastros
    Route::apiResource('orgaos', ApiOrgaoController::class);
    Route::apiResource('fornecedores', ApiFornecedorController::class);
    Route::apiResource('documentos-habilitacao', ApiDocumentoHabilitacaoController::class);
    
    // Relatórios
    Route::get('/relatorios/financeiro', [ApiRelatorioFinanceiroController::class, 'index']);
});
