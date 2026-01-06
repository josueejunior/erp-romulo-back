<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Http\Controllers\EmpresaSelecaoController;
use App\Modules\Processo\Controllers\ProcessoController;
use App\Modules\Orgao\Controllers\OrgaoController;
use App\Modules\Fornecedor\Controllers\FornecedorController;
use App\Modules\Calendario\Controllers\CalendarioDisputasController;
use App\Modules\Documento\Controllers\DocumentoHabilitacaoController;
// use App\Modules\Empresa\Controllers\EmpresaController; // TODO: Criar se necessário
use App\Modules\Contrato\Controllers\ContratoController;
use App\Modules\AutorizacaoFornecimento\Controllers\AutorizacaoFornecimentoController;
use App\Modules\Empenho\Controllers\EmpenhoController;
use App\Modules\NotaFiscal\Controllers\NotaFiscalController;
use App\Modules\Relatorio\Controllers\RelatorioFinanceiroController;
use App\Modules\Orgao\Controllers\SetorController;
use App\Modules\Processo\Controllers\ProcessoItemController;
use App\Modules\Orcamento\Controllers\OrcamentoController;
use App\Modules\Orcamento\Controllers\FormacaoPrecoController;
use App\Modules\Processo\Controllers\DisputaController;
use App\Modules\Processo\Controllers\JulgamentoController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'empresa.ativa'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Seleção de empresa
    Route::get('/empresas/selecionar', [EmpresaSelecaoController::class, 'selecionar'])->name('empresas.selecionar');
    Route::post('/empresas/definir', [EmpresaSelecaoController::class, 'definir'])->name('empresas.definir');

    // Processos (resource removido - conflita com API, usar apenas rotas específicas)
    // Route::resource('processos', ProcessoController::class); // ❌ Removido - conflito com api.php
    Route::post('/processos/{processo}/marcar-vencido', [ProcessoController::class, 'marcarVencido'])->name('processos.marcar-vencido');
    Route::post('/processos/{processo}/marcar-perdido', [ProcessoController::class, 'marcarPerdido'])->name('processos.marcar-perdido');
    
    // Disputa
    Route::get('/processos/{processo}/disputa', [DisputaController::class, 'edit'])->name('disputas.edit');
    Route::put('/processos/{processo}/disputa', [DisputaController::class, 'update'])->name('disputas.update');
    
    // Julgamento e Habilitação
    Route::get('/processos/{processo}/julgamento', [JulgamentoController::class, 'edit'])->name('julgamentos.edit');
    Route::put('/processos/{processo}/julgamento', [JulgamentoController::class, 'update'])->name('julgamentos.update');
    
    // Itens do Processo
    Route::get('/processos/{processo}/itens/create', [ProcessoItemController::class, 'create'])->name('processo-itens.create');
    Route::post('/processos/{processo}/itens', [ProcessoItemController::class, 'storeWeb'])->name('processo-itens.store');
    Route::get('/processos/{processo}/itens/{item}/edit', [ProcessoItemController::class, 'edit'])->name('processo-itens.edit');
    Route::put('/processos/{processo}/itens/{item}', [ProcessoItemController::class, 'updateWeb'])->name('processo-itens.update');
    Route::delete('/processos/{processo}/itens/{item}', [ProcessoItemController::class, 'destroyWeb'])->name('processo-itens.destroy');
    
    // Orçamentos (rotas web - nomes prefixados para evitar conflito com API)
    Route::get('/processos/{processo}/itens/{item}/orcamentos/create', [OrcamentoController::class, 'create'])->name('web.orcamentos.create');
    Route::post('/processos/{processo}/itens/{item}/orcamentos', [OrcamentoController::class, 'store'])->name('web.orcamentos.store');
    Route::get('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/edit', [OrcamentoController::class, 'edit'])->name('web.orcamentos.edit');
    Route::put('/processos/{processo}/itens/{item}/orcamentos/{orcamento}', [OrcamentoController::class, 'update'])->name('web.orcamentos.update');
    Route::delete('/processos/{processo}/itens/{item}/orcamentos/{orcamento}', [OrcamentoController::class, 'destroy'])->name('web.orcamentos.destroy');
    
    // Formação de Preços (rotas web - nomes prefixados para evitar conflito com API)
    Route::get('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco/create', [FormacaoPrecoController::class, 'create'])->name('web.formacao-precos.create');
    Route::post('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco', [FormacaoPrecoController::class, 'store'])->name('web.formacao-precos.store');
    Route::get('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco/{formacaoPreco}/edit', [FormacaoPrecoController::class, 'edit'])->name('web.formacao-precos.edit');
    Route::put('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco/{formacaoPreco}', [FormacaoPrecoController::class, 'update'])->name('web.formacao-precos.update');

    // Calendário
    Route::get('/calendario', [CalendarioDisputasController::class, 'index'])->name('calendario.index');

    // Cadastros (rotas web - nomes prefixados para evitar conflito com API)
    Route::resource('orgaos', OrgaoController::class)->names([
        'index' => 'web.orgaos.index',
        'create' => 'web.orgaos.create',
        'store' => 'web.orgaos.store',
        'show' => 'web.orgaos.show',
        'edit' => 'web.orgaos.edit',
        'update' => 'web.orgaos.update',
        'destroy' => 'web.orgaos.destroy',
    ]);
    Route::resource('setors', SetorController::class)->except(['index', 'show'])->names([
        'create' => 'web.setors.create',
        'store' => 'web.setors.store',
        'edit' => 'web.setors.edit',
        'update' => 'web.setors.update',
        'destroy' => 'web.setors.destroy',
    ]);
    Route::resource('fornecedores', FornecedorController::class)->names([
        'index' => 'web.fornecedores.index',
        'create' => 'web.fornecedores.create',
        'store' => 'web.fornecedores.store',
        'show' => 'web.fornecedores.show',
        'edit' => 'web.fornecedores.edit',
        'update' => 'web.fornecedores.update',
        'destroy' => 'web.fornecedores.destroy',
    ]);
    Route::resource('documentos-habilitacao', DocumentoHabilitacaoController::class)->names([
        'index' => 'web.documentos-habilitacao.index',
        'create' => 'web.documentos-habilitacao.create',
        'store' => 'web.documentos-habilitacao.store',
        'show' => 'web.documentos-habilitacao.show',
        'edit' => 'web.documentos-habilitacao.edit',
        'update' => 'web.documentos-habilitacao.update',
        'destroy' => 'web.documentos-habilitacao.destroy',
    ]);
    // Route::resource('empresas', EmpresaController::class); // TODO: Criar EmpresaController se necessário

    // Execução - Contratos (scoped to processo) - prefixo web. para evitar conflito
    Route::get('/processos/{processo}/contratos/create', [ContratoController::class, 'create'])->name('web.contratos.create');
    Route::post('/processos/{processo}/contratos', [ContratoController::class, 'store'])->name('web.contratos.store');
    Route::get('/processos/{processo}/contratos/{contrato}', [ContratoController::class, 'show'])->name('web.contratos.show');
    Route::get('/processos/{processo}/contratos/{contrato}/edit', [ContratoController::class, 'edit'])->name('web.contratos.edit');
    Route::put('/processos/{processo}/contratos/{contrato}', [ContratoController::class, 'update'])->name('web.contratos.update');
    Route::delete('/processos/{processo}/contratos/{contrato}', [ContratoController::class, 'destroy'])->name('web.contratos.destroy');
    
    // Autorizações de Fornecimento (scoped to processo) - prefixo web. para evitar conflito
    Route::get('/processos/{processo}/autorizacoes-fornecimento/create', [AutorizacaoFornecimentoController::class, 'create'])->name('web.autorizacoes-fornecimento.create');
    Route::post('/processos/{processo}/autorizacoes-fornecimento', [AutorizacaoFornecimentoController::class, 'store'])->name('web.autorizacoes-fornecimento.store');
    Route::get('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}', [AutorizacaoFornecimentoController::class, 'show'])->name('web.autorizacoes-fornecimento.show');
    Route::get('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}/edit', [AutorizacaoFornecimentoController::class, 'edit'])->name('web.autorizacoes-fornecimento.edit');
    Route::put('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}', [AutorizacaoFornecimentoController::class, 'update'])->name('web.autorizacoes-fornecimento.update');
    Route::delete('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}', [AutorizacaoFornecimentoController::class, 'destroy'])->name('web.autorizacoes-fornecimento.destroy');
    
    // Empenhos (scoped to processo) - prefixo web. para evitar conflito
    Route::get('/processos/{processo}/empenhos/create', [EmpenhoController::class, 'create'])->name('web.empenhos.create');
    Route::post('/processos/{processo}/empenhos', [EmpenhoController::class, 'store'])->name('web.empenhos.store');
    Route::get('/processos/{processo}/empenhos/{empenho}', [EmpenhoController::class, 'show'])->name('web.empenhos.show');
    Route::get('/processos/{processo}/empenhos/{empenho}/edit', [EmpenhoController::class, 'edit'])->name('web.empenhos.edit');
    Route::put('/processos/{processo}/empenhos/{empenho}', [EmpenhoController::class, 'update'])->name('web.empenhos.update');
    Route::delete('/processos/{processo}/empenhos/{empenho}', [EmpenhoController::class, 'destroy'])->name('web.empenhos.destroy');
    
    // Notas Fiscais (scoped to processo) - prefixo web. para evitar conflito
    Route::get('/processos/{processo}/notas-fiscais/create', [NotaFiscalController::class, 'create'])->name('web.notas-fiscais.create');
    Route::post('/processos/{processo}/notas-fiscais', [NotaFiscalController::class, 'store'])->name('web.notas-fiscais.store');
    Route::get('/processos/{processo}/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'show'])->name('web.notas-fiscais.show');
    Route::get('/processos/{processo}/notas-fiscais/{notaFiscal}/edit', [NotaFiscalController::class, 'edit'])->name('web.notas-fiscais.edit');
    Route::put('/processos/{processo}/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'update'])->name('web.notas-fiscais.update');
    Route::delete('/processos/{processo}/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'destroy'])->name('web.notas-fiscais.destroy');

    // Relatórios
    Route::get('/relatorios/financeiro', [RelatorioFinanceiroController::class, 'index'])->name('relatorios.financeiro');
});

require __DIR__.'/auth.php';
