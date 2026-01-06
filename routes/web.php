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
    
    // Orçamentos
    Route::get('/processos/{processo}/itens/{item}/orcamentos/create', [OrcamentoController::class, 'create'])->name('orcamentos.create');
    Route::post('/processos/{processo}/itens/{item}/orcamentos', [OrcamentoController::class, 'store'])->name('orcamentos.store');
    Route::get('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/edit', [OrcamentoController::class, 'edit'])->name('orcamentos.edit');
    Route::put('/processos/{processo}/itens/{item}/orcamentos/{orcamento}', [OrcamentoController::class, 'update'])->name('orcamentos.update');
    Route::delete('/processos/{processo}/itens/{item}/orcamentos/{orcamento}', [OrcamentoController::class, 'destroy'])->name('orcamentos.destroy');
    
    // Formação de Preços
    Route::get('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco/create', [FormacaoPrecoController::class, 'create'])->name('formacao-precos.create');
    Route::post('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco', [FormacaoPrecoController::class, 'store'])->name('formacao-precos.store');
    Route::get('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco/{formacaoPreco}/edit', [FormacaoPrecoController::class, 'edit'])->name('formacao-precos.edit');
    Route::put('/processos/{processo}/itens/{item}/orcamentos/{orcamento}/formacao-preco/{formacaoPreco}', [FormacaoPrecoController::class, 'update'])->name('formacao-precos.update');

    // Calendário
    Route::get('/calendario', [CalendarioDisputasController::class, 'index'])->name('calendario.index');

    // Cadastros
    Route::resource('orgaos', OrgaoController::class);
    Route::resource('setors', SetorController::class)->except(['index', 'show']);
    Route::resource('fornecedores', FornecedorController::class);
    Route::resource('documentos-habilitacao', DocumentoHabilitacaoController::class);
    // Route::resource('empresas', EmpresaController::class); // TODO: Criar EmpresaController se necessário

    // Execução - Contratos (scoped to processo)
    Route::get('/processos/{processo}/contratos/create', [ContratoController::class, 'create'])->name('contratos.create');
    Route::post('/processos/{processo}/contratos', [ContratoController::class, 'store'])->name('contratos.store');
    Route::get('/processos/{processo}/contratos/{contrato}', [ContratoController::class, 'show'])->name('contratos.show');
    Route::get('/processos/{processo}/contratos/{contrato}/edit', [ContratoController::class, 'edit'])->name('contratos.edit');
    Route::put('/processos/{processo}/contratos/{contrato}', [ContratoController::class, 'update'])->name('contratos.update');
    Route::delete('/processos/{processo}/contratos/{contrato}', [ContratoController::class, 'destroy'])->name('contratos.destroy');
    
    // Autorizações de Fornecimento (scoped to processo)
    Route::get('/processos/{processo}/autorizacoes-fornecimento/create', [AutorizacaoFornecimentoController::class, 'create'])->name('autorizacoes-fornecimento.create');
    Route::post('/processos/{processo}/autorizacoes-fornecimento', [AutorizacaoFornecimentoController::class, 'store'])->name('autorizacoes-fornecimento.store');
    Route::get('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}', [AutorizacaoFornecimentoController::class, 'show'])->name('autorizacoes-fornecimento.show');
    Route::get('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}/edit', [AutorizacaoFornecimentoController::class, 'edit'])->name('autorizacoes-fornecimento.edit');
    Route::put('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}', [AutorizacaoFornecimentoController::class, 'update'])->name('autorizacoes-fornecimento.update');
    Route::delete('/processos/{processo}/autorizacoes-fornecimento/{autorizacaoFornecimento}', [AutorizacaoFornecimentoController::class, 'destroy'])->name('autorizacoes-fornecimento.destroy');
    
    // Empenhos (scoped to processo)
    Route::get('/processos/{processo}/empenhos/create', [EmpenhoController::class, 'create'])->name('empenhos.create');
    Route::post('/processos/{processo}/empenhos', [EmpenhoController::class, 'store'])->name('empenhos.store');
    Route::get('/processos/{processo}/empenhos/{empenho}', [EmpenhoController::class, 'show'])->name('empenhos.show');
    Route::get('/processos/{processo}/empenhos/{empenho}/edit', [EmpenhoController::class, 'edit'])->name('empenhos.edit');
    Route::put('/processos/{processo}/empenhos/{empenho}', [EmpenhoController::class, 'update'])->name('empenhos.update');
    Route::delete('/processos/{processo}/empenhos/{empenho}', [EmpenhoController::class, 'destroy'])->name('empenhos.destroy');
    
    // Notas Fiscais (scoped to processo)
    Route::get('/processos/{processo}/notas-fiscais/create', [NotaFiscalController::class, 'create'])->name('notas-fiscais.create');
    Route::post('/processos/{processo}/notas-fiscais', [NotaFiscalController::class, 'store'])->name('notas-fiscais.store');
    Route::get('/processos/{processo}/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'show'])->name('notas-fiscais.show');
    Route::get('/processos/{processo}/notas-fiscais/{notaFiscal}/edit', [NotaFiscalController::class, 'edit'])->name('notas-fiscais.edit');
    Route::put('/processos/{processo}/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'update'])->name('notas-fiscais.update');
    Route::delete('/processos/{processo}/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'destroy'])->name('notas-fiscais.destroy');

    // Relatórios
    Route::get('/relatorios/financeiro', [RelatorioFinanceiroController::class, 'index'])->name('relatorios.financeiro');
});

require __DIR__.'/auth.php';
