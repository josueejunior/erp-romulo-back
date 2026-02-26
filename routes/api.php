<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
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
use App\Modules\Produto\Controllers\ProdutoController as ApiProdutoController;
use App\Modules\Custo\Controllers\CustoIndiretoController as ApiCustoIndiretoController;
use App\Modules\Documento\Controllers\DocumentoHabilitacaoController as ApiDocumentoHabilitacaoController;
use App\Modules\Dashboard\Controllers\DashboardController as ApiDashboardController;
use App\Modules\Relatorio\Controllers\RelatorioFinanceiroController as ApiRelatorioFinanceiroController;
use App\Modules\Empresa\Controllers\TenantController;
use App\Modules\Calendario\Controllers\CalendarioDisputasController as ApiCalendarioDisputasController;
use App\Modules\Calendario\Controllers\CalendarioController as ApiCalendarioController;
use App\Modules\Processo\Controllers\ExportacaoController as ApiExportacaoController;
use App\Modules\Processo\Controllers\SaldoController as ApiSaldoController;
use App\Modules\Auth\Controllers\UserController as ApiUserController;
use App\Modules\Assinatura\Controllers\PlanoController as ApiPlanoController;
use App\Modules\Assinatura\Controllers\AssinaturaController as ApiAssinaturaController;
use App\Modules\Payment\Controllers\PaymentController as ApiPaymentController;
use App\Modules\Payment\Controllers\WebhookController as ApiWebhookController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminBackupController;
use App\Http\Controllers\Admin\AdminComissoesController;
use App\Http\Controllers\Admin\AdminTenantsIncompletosController;
use App\Http\Controllers\Admin\AdminDatabaseController;
use App\Http\Controllers\Admin\AdminCrossTenantController;
use App\Http\Controllers\Api\ConviteUsuarioController;

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

// Health check endpoints (sem autenticação, sem rate limiting agressivo)
Route::prefix('health')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\HealthController::class, 'check']);
    Route::get('/detailed', [\App\Http\Controllers\Api\HealthController::class, 'detailed']);
});

Route::prefix('v1')->group(function () {
    // Rotas públicas (central) - Gerenciamento de Tenants/Empresas
    Route::module('tenants', TenantController::class, 'tenant')
        ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);

    // Rotas públicas - Planos (podem ser visualizados sem autenticação)
    // IMPORTANTE: Estas rotas devem ser públicas para a tela de cadastro funcionar
    // Garantir explicitamente que não passem por middleware de autenticação
    Route::withoutMiddleware(['jwt.auth', 'auth.context', 'auth.sanctum'])
        ->group(function () {
            Route::get('/planos', [ApiPlanoController::class, 'list']);
            Route::get('/planos/{id}', [ApiPlanoController::class, 'get'])->where('id', '[0-9]+');
        });

    // Rotas públicas (autenticação)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    
    // Upload público (para cadastro público)
    Route::post('/upload/image', [\App\Http\Controllers\UploadController::class, 'uploadImage']);
    // Servir imagem de upload via URL assinada (evita 403 em /storage; usado por anexos de tickets)
    Route::get('/serve-upload', [\App\Http\Controllers\UploadController::class, 'serveImage'])
        ->middleware('signed')
        ->name('serve-upload');
    
    // Cadastro público (cria tenant + assinatura + usuário)
    Route::post('/cadastro-publico', [\App\Http\Controllers\Public\CadastroPublicoController::class, 'store'])
        ->middleware(['sanitize.inputs']);
    
    // Consulta pública de CNPJ (para cadastro público)
    Route::get('/cadastro-publico/consultar-cnpj/{cnpj}', [\App\Http\Controllers\Public\CadastroPublicoController::class, 'consultarCnpj']);
    
    // Verificação de email em tempo real (para validação onBlur)
    Route::get('/cadastro-publico/verificar-email/{email}', [\App\Http\Controllers\Public\CadastroPublicoController::class, 'verificarEmail']);

    // Cadastro público de afiliados (sem autenticação)
    Route::post('/afiliados/cadastro-publico', [\App\Http\Controllers\Public\CadastroAfiliadoController::class, 'store'])
        ->middleware(['sanitize.inputs']);

    // 🔥 NOVA ARQUITETURA: Pipeline previsível e testável
    // 
    // CAMADA 3: AuthenticateJWT - Valida JWT e define user
    // CAMADA 4: BuildAuthContext - Cria identidade de autenticação
    // CAMADA 5: ResolveTenantContext - Resolve e inicializa tenant
    // CAMADA 6: BootstrapApplicationContext - Bootstrap de empresa/assinatura
    // 
    // Cada middleware faz UMA coisa e falha o mais cedo possível
    Route::middleware([
        'jwt.auth',                    // CAMADA 3: Autenticação JWT
        'auth.context',                // CAMADA 4: Identidade
        'tenant.context',              // CAMADA 5: Resolve e inicializa tenant (ANTES do BootstrapApplicationContext)
        \App\Http\Middleware\BootstrapApplicationContext::class,  // CAMADA 6: Bootstrap empresa (usa tenant inicializado)
    ])->group(function () {
        // Rotas que NÃO precisam de assinatura (exceções)
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);
        
        // Rota de compatibilidade para /user/roles (redireciona para /users/roles)
        Route::get('/user/roles', [\App\Modules\Auth\Controllers\FixUserRolesController::class, 'getCurrentUserRoles']);
        
        // Obter roles do usuário atual (precisa estar fora do CheckSubscription)
        Route::get('/users/roles', [\App\Modules\Auth\Controllers\FixUserRolesController::class, 'getCurrentUserRoles']);
        
        // Trocar empresa ativa (precisa estar fora do CheckSubscription para permitir troca mesmo sem assinatura)
        Route::put('/users/empresa-ativa', [ApiUserController::class, 'switchEmpresaAtiva']);
        
        // 🔥 CROSS-TENANT: Rotas para troca de tenant e convites (não precisa de assinatura)
        Route::get('/users/meus-tenants', [ConviteUsuarioController::class, 'meusTenants']);
        Route::post('/users/trocar-tenant', [ConviteUsuarioController::class, 'trocarTenant']);
        Route::post('/users/convidar', [ConviteUsuarioController::class, 'convidar']);
        
        // Assinaturas e Pagamentos
        // 🔥 IMPORTANTE:
        // - Não exigem assinatura ativa (podem ser usados para contratar/renovar)
        // - MAS exigem onboarding concluído para planos gratuitos (protegidos por CheckOnboarding)
        Route::middleware([\App\Http\Middleware\CheckOnboarding::class])->group(function () {
            Route::prefix('assinaturas')->group(function () {
                Route::get('/atual', [ApiAssinaturaController::class, 'atual']);
                Route::get('/status', [ApiAssinaturaController::class, 'status']);
                Route::get('/historico-pagamentos', [ApiAssinaturaController::class, 'historicoPagamentos']);
                Route::get('/', [ApiAssinaturaController::class, 'index']);
                Route::post('/', [ApiAssinaturaController::class, 'store']);
                Route::post('/trocar-plano', [ApiAssinaturaController::class, 'trocarPlano']);
                Route::post('/simular-troca-plano', [ApiAssinaturaController::class, 'simularTrocaPlano']);
                Route::post('/{assinatura}/renovar', [ApiAssinaturaController::class, 'renovar']);
                Route::post('/{assinatura}/cancelar', [ApiAssinaturaController::class, 'cancelar']);
            });

            Route::prefix('payments')->group(function () {
                Route::post('/processar-assinatura', [ApiPaymentController::class, 'processarAssinatura'])->middleware('throttle:10,1');
                Route::get('/{externalId}/status', [ApiPaymentController::class, 'checkStatus'])->middleware('throttle:30,1');
            });
        });

        // Cupons
        Route::get('/cupons/{codigo}/validar', [\App\Modules\Assinatura\Controllers\CupomController::class, 'validar']);
        
        // Notificações (não precisa de assinatura ativa)
        Route::get('/notifications', [\App\Modules\Notification\Controllers\NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [\App\Modules\Notification\Controllers\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Modules\Notification\Controllers\NotificationController::class, 'markAllAsRead']);
        
        // Onboarding (usuários autenticados)
        Route::prefix('onboarding')->group(function () {
            Route::get('/status', [\App\Modules\Onboarding\Controllers\OnboardingController::class, 'status']);
            Route::post('/marcar-etapa', [\App\Modules\Onboarding\Controllers\OnboardingController::class, 'marcarEtapa']);
            Route::post('/concluir', [\App\Modules\Onboarding\Controllers\OnboardingController::class, 'concluir']);
        });
        
        // Configurações (usuários autenticados - não precisa de assinatura ativa)
        Route::prefix('configuracoes')->group(function () {
            Route::get('/tenant', [\App\Http\Controllers\Public\ConfiguracoesController::class, 'getTenant']); // GET para obter dados (se necessário)
            Route::put('/tenant', [\App\Http\Controllers\Public\ConfiguracoesController::class, 'atualizarTenant']);
            Route::get('/notificacoes', [\App\Http\Controllers\Public\ConfiguracoesController::class, 'getNotificacoes']);
            Route::put('/notificacoes', [\App\Http\Controllers\Public\ConfiguracoesController::class, 'atualizarNotificacoes']);
        });

        // Ticket de suporte (abertura e acompanhamento, sem exigir assinatura ativa)
        Route::get('/tickets', [\App\Http\Controllers\Public\SupportTicketController::class, 'index']);
        Route::get('/tickets/{id}', [\App\Http\Controllers\Public\SupportTicketController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/tickets', [\App\Http\Controllers\Public\SupportTicketController::class, 'store']);
        
        // Buscar cupom automático de afiliado (opcional - pode não existir)
        // 🔥 CORREÇÃO: Removido middleware onboarding.completo - rota é opcional e não deve bloquear
        Route::get('/planos/cupom-automatico', [\App\Http\Controllers\Public\AfiliadoReferenciaController::class, 'buscarCupomAutomatico']);
        
        // Rotas de comissões para afiliados (autenticadas)
        Route::prefix('afiliado')->middleware(['auth:api'])->group(function () {
            Route::get('/comissoes', [\App\Http\Controllers\Afiliado\AfiliadoComissoesController::class, 'index']);
            Route::get('/comissoes/resumo', [\App\Http\Controllers\Afiliado\AfiliadoComissoesController::class, 'resumo']);
        });

        // Rotas que PRECISAM de assinatura ativa
        Route::middleware([\App\Http\Middleware\CheckSubscription::class])->group(function () {
            // Dashboard
            Route::get('/dashboard', [ApiDashboardController::class, 'index']);
            
            // Empenhos (listar todos - sem precisar de processo específico)
            Route::get('/empenhos', [ApiEmpenhoController::class, 'listAll']);
            
            // Notas Fiscais (listar todas - sem precisar de processo específico)
            Route::get('/notas-fiscais', [ApiNotaFiscalController::class, 'listAll']);
            
            // Orçamentos (listar todos - sem precisar de processo específico)
            Route::get('/orcamentos', [ApiOrcamentoController::class, 'listAll']);
            
            // Recursos estáticos (enums, listas)
            Route::get('/unidades-medida', [ApiProcessoItemController::class, 'unidadesMedida']);
            
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
                
                // Documentos de habilitação
                Route::get('/documentos', [ApiProcessoController::class, 'listarDocumentos']);
                Route::post('/documentos/importar', [ApiProcessoController::class, 'importarDocumentos']);
                Route::post('/documentos/sincronizar', [ApiProcessoController::class, 'sincronizarDocumentos']);
                Route::post('/documentos/custom', [ApiProcessoController::class, 'criarDocumentoCustom']);
                Route::match(['put', 'patch'], '/documentos/{processoDocumento}', [ApiProcessoController::class, 'atualizarDocumento']);
                Route::get('/documentos/{processoDocumento}/download', [ApiProcessoController::class, 'downloadDocumento']);
                Route::post('/marcar-perdido', [ApiProcessoController::class, 'marcarPerdido']);
                Route::post('/confirmar-pagamento', [ApiProcessoController::class, 'confirmarPagamento']);
                Route::get('/confirmacoes-pagamento', [ApiProcessoController::class, 'historicoConfirmacoes']);
                Route::get('/sugerir-status', [ApiProcessoController::class, 'sugerirStatus']);
                Route::get('/ficha-export', [ApiProcessoController::class, 'fichaTecnicaExport']);
                
                // Notas rápidas do processo (anotações de execução)
                Route::get('/notas', [\App\Modules\Processo\Controllers\ProcessoNotaController::class, 'index']);
                Route::post('/notas', [\App\Modules\Processo\Controllers\ProcessoNotaController::class, 'store']);
                Route::delete('/notas/{nota}', [\App\Modules\Processo\Controllers\ProcessoNotaController::class, 'destroy']);
                Route::get('/download-edital', [ApiProcessoController::class, 'downloadEdital']);
                
                // Exportação
                Route::get('/exportar/proposta-comercial', [ApiExportacaoController::class, 'propostaComercial']);
                Route::get('/exportar/catalogo-ficha-tecnica', [ApiExportacaoController::class, 'catalogoFichaTecnica']);
                
                // Saldo
                Route::get('/saldo', [ApiSaldoController::class, 'show']);
                Route::get('/saldo-vencido', [ApiSaldoController::class, 'saldoVencido']);
                Route::get('/saldo-vinculado', [ApiSaldoController::class, 'saldoVinculado']);
                Route::get('/saldo-empenhado', [ApiSaldoController::class, 'saldoEmpenhado']);
                Route::get('/saldo/comparativo-custos', [ApiSaldoController::class, 'comparativoCustos']);
                Route::post('/saldo/recalcular-valores-itens', [ApiSaldoController::class, 'recalcularValoresItens']);
                
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
                                // 🔥 CORREÇÃO: Adicionar rota POST explícita sem parâmetro opcional para evitar problemas com Route Model Binding
                                Route::post('/formacao-preco', [ApiFormacaoPrecoController::class, 'store'])->name('formacao-preco.store');
                                Route::module('formacao-preco', ApiFormacaoPrecoController::class, 'formacaoPreco')
                                    ->methods(['list' => 'list', 'get' => 'get', 'update' => 'update', 'destroy' => 'destroy'])
                                    ->except(['store']); // Excluir store do module para usar a rota explícita acima
                            });
                        
                        // Vínculos (Contrato/AF/Empenho)
                        Route::get('/vinculos', [\App\Modules\Processo\Controllers\ProcessoItemVinculoController::class, 'list']);
                        Route::get('/vinculos/{vinculo}', [\App\Modules\Processo\Controllers\ProcessoItemVinculoController::class, 'get']);
                        Route::post('/vinculos', [\App\Modules\Processo\Controllers\ProcessoItemVinculoController::class, 'store']);
                        Route::put('/vinculos/{vinculo}', [\App\Modules\Processo\Controllers\ProcessoItemVinculoController::class, 'update']);
                        Route::delete('/vinculos/{vinculo}', [\App\Modules\Processo\Controllers\ProcessoItemVinculoController::class, 'destroy']);
                    });
                Route::post('/itens/importar', [ApiProcessoItemController::class, 'importar']);
                
                // Endpoints específicos para disputas e julgamentos
                Route::patch('/itens/{item}/valor-final-disputa', [ApiProcessoItemController::class, 'atualizarValorFinalDisputa']);
                Route::patch('/itens/{item}/valor-negociado', [ApiProcessoItemController::class, 'atualizarValorNegociado']);
                Route::patch('/itens/{item}/status', [ApiProcessoItemController::class, 'atualizarStatus']);
                
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
                
                // Empenhos (dentro de processo)
                Route::module('empenhos', ApiEmpenhoController::class, 'empenho')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
                
                // Rota para concluir empenho (deve estar após o module para evitar conflito)
                // Está dentro do grupo processos/{processo}, então o caminho é relativo
                Route::post('empenhos/{empenho}/concluir', [ApiEmpenhoController::class, 'concluir'])
                    ->name('empenhos.concluir');
                
                // Notas Fiscais
                Route::module('notas-fiscais', ApiNotaFiscalController::class, 'notaFiscal')
                    ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
                Route::patch('/notas-fiscais/{notaFiscal}/pagar', [ApiNotaFiscalController::class, 'pagar']);
            });
            
            // Contratos
            Route::prefix('contratos')->group(function () {
                Route::get('/', [ApiContratoController::class, 'listarTodos']); // Lista todos os contratos
            });
            
            // Cadastros
            Route::module('orgaos', ApiOrgaoController::class, 'orgao');
            
            // Consulta de CNPJ na Receita Federal para órgãos
            Route::get('/orgaos/consultar-cnpj/{cnpj}', [ApiOrgaoController::class, 'consultarCnpj']);
            
            // Rotas para responsáveis de órgãos
            Route::prefix('orgaos/{orgao}')->group(function () {
                Route::get('responsaveis', [\App\Modules\Orgao\Controllers\OrgaoResponsavelController::class, 'index']);
                Route::post('responsaveis', [\App\Modules\Orgao\Controllers\OrgaoResponsavelController::class, 'store']);
                Route::put('responsaveis/{responsavel}', [\App\Modules\Orgao\Controllers\OrgaoResponsavelController::class, 'update']);
                Route::delete('responsaveis/{responsavel}', [\App\Modules\Orgao\Controllers\OrgaoResponsavelController::class, 'destroy']);
            });
            
            Route::module('setors', ApiSetorController::class, 'setor')
                ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
        
            Route::module('fornecedores', ApiFornecedorController::class, 'fornecedor')
                ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
            
            // Consulta de CNPJ na Receita Federal
            Route::get('/fornecedores/consultar-cnpj/{cnpj}', [ApiFornecedorController::class, 'consultarCnpj']);
            
            Route::module('produtos', ApiProdutoController::class, 'produto')
                ->methods(['list' => 'index', 'get' => 'show', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
            
            Route::module('custos-indiretos', ApiCustoIndiretoController::class, 'id')
                ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
                ->group(function () {
                    Route::get('/resumo', [ApiCustoIndiretoController::class, 'resumo']);
                });
            
            // Rota de compatibilidade para /custos-indiretos-resumo
            Route::get('/custos-indiretos-resumo', [ApiCustoIndiretoController::class, 'resumo']);
            
            Route::module('documentos-habilitacao', ApiDocumentoHabilitacaoController::class, 'documentoHabilitacao')
                ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy']);
            
            // Rotas adicionais para documentos de habilitação
            Route::prefix('documentos-habilitacao')->group(function () {
                Route::get('vencendo', [ApiDocumentoHabilitacaoController::class, 'vencendo']);
                Route::get('vencidos', [ApiDocumentoHabilitacaoController::class, 'vencidos']);
            });
            
            // Usuários
            Route::module('users', ApiUserController::class, 'user')
                ->methods(['list' => 'list', 'get' => 'get', 'store' => 'store', 'update' => 'update', 'destroy' => 'destroy'])
                ->group(function () {
                    // Debug/Correção de roles (apenas fix-role precisa de assinatura)
                    // Nota: /users/roles foi movido para fora do CheckSubscription (linha ~81)
                    Route::post('/fix-role', [\App\Modules\Auth\Controllers\FixUserRolesController::class, 'fixCurrentUserRole']);
                    // Nota: switchEmpresaAtiva foi movido para fora do CheckSubscription (linha ~85)
                });
            
            // Relatórios
            Route::prefix('relatorios')->group(function () {
                Route::get('/financeiro', [ApiRelatorioFinanceiroController::class, 'index']);
                Route::get('/gestao-mensal', [ApiRelatorioFinanceiroController::class, 'gestaoMensal']);
                Route::get('/financeiro/exportar', [ApiRelatorioFinanceiroController::class, 'exportar']);
                
                // Relatórios de Orçamentos
                // ✅ CheckSubscription já valida a assinatura ativa
                Route::get('/orcamentos', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'index']);
                Route::get('/orcamentos/export', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'export']);
                Route::get('/orcamentos/por-fornecedor', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'porFornecedor']);
                Route::get('/orcamentos/por-status', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'porStatus']);
            });
        }); // Fim do grupo com CheckSubscription
    });

    // Webhooks (públicos, sem autenticação)
    Route::prefix('webhooks')->group(function () {
        Route::post('/mercadopago', [ApiWebhookController::class, 'mercadopago'])->middleware('throttle:600,1');
    });
});

// Rotas do Painel Admin Central (fora do tenant e fora do v1)
// 🔥 IMPORTANTE: Rotas admin devem estar dentro do prefixo 'api' mas fora do 'v1'
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    
    // 🔥 NOVA ARQUITETURA: Pipeline para admin
    // 
    // CAMADA 3: AuthenticateJWT - Valida JWT e define AdminUser
    // CAMADA 4: BuildAuthContext - Cria identidade de autenticação
    // CAMADA 7: EnsureAdmin - Valida se é AdminUser
    // 
    // Admin não precisa de tenant/empresa, então pulamos essas camadas
    Route::middleware([
        'jwt.auth',                    // CAMADA 3: Autenticação JWT
        'auth.context',                // CAMADA 4: Identidade
        'admin'                        // CAMADA 7: Validação admin
    ])->group(function () {
        // Autenticação admin
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::match(['post', 'put'], '/perfil', [AdminAuthController::class, 'atualizarPerfil']);
        Route::put('/perfil/senha', [AdminAuthController::class, 'alterarSenha']);
        
        // Rotas globais para usuários (sem necessidade de especificar tenant)
        Route::prefix('usuarios')->group(function () {
            Route::get('/', [AdminUserController::class, 'indexGlobal']);
            Route::get('/{userId}', [AdminUserController::class, 'showGlobal'])->where('userId', '[0-9]+');
            Route::delete('/{userId}', [AdminUserController::class, 'destroyGlobal'])->where('userId', '[0-9]+');
            Route::post('/{userId}/reativar', [AdminUserController::class, 'reactivateGlobal'])->where('userId', '[0-9]+');
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
                    Route::get('/buscar-por-email', [AdminUserController::class, 'buscarPorEmail']); // 🔥 Buscar usuário existente para vincular
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

        // Gerenciamento de planos
        Route::prefix('planos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminPlanoController::class, 'index']);
            Route::get('/{plano}', [\App\Http\Controllers\Admin\AdminPlanoController::class, 'show'])->where('plano', '[0-9]+');
            Route::post('/', [\App\Http\Controllers\Admin\AdminPlanoController::class, 'store']);
            Route::put('/{plano}', [\App\Http\Controllers\Admin\AdminPlanoController::class, 'update'])->where('plano', '[0-9]+');
            Route::delete('/{plano}', [\App\Http\Controllers\Admin\AdminPlanoController::class, 'destroy'])->where('plano', '[0-9]+');
        });

        // Gerenciamento de assinaturas
        Route::prefix('assinaturas')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'store']);
            Route::get('/tenants', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'tenants']);
            Route::get('/{tenantId}/{assinaturaId}', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'show'])
                ->where(['tenantId' => '[0-9]+', 'assinaturaId' => '[0-9]+']);
            Route::put('/{tenantId}/{assinaturaId}', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'update'])
                ->where(['tenantId' => '[0-9]+', 'assinaturaId' => '[0-9]+']);
            Route::post('/{tenantId}/{assinaturaId}/trocar-plano', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'trocarPlano'])
                ->where(['tenantId' => '[0-9]+', 'assinaturaId' => '[0-9]+']);
        });

        // Gerenciamento de cupons
        Route::prefix('cupons')->group(function () {
            Route::get('/', [\App\Modules\Assinatura\Controllers\CupomController::class, 'index']);
            Route::post('/', [\App\Modules\Assinatura\Controllers\CupomController::class, 'store']);
            Route::put('/{cupom}', [\App\Modules\Assinatura\Controllers\CupomController::class, 'update']);
            Route::delete('/{cupom}', [\App\Modules\Assinatura\Controllers\CupomController::class, 'destroy']);
        });

        // 🆕 Gerenciamento de afiliados
        Route::prefix('afiliados')->group(function () {
            Route::get('/', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'index']);
            Route::post('/', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'store']);
            Route::get('/{id}', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'update'])->where('id', '[0-9]+');
            Route::delete('/{id}', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'destroy'])->where('id', '[0-9]+');
            Route::get('/{id}/detalhes', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'detalhes'])->where('id', '[0-9]+');
        });

        // Gerenciamento de comissões de afiliados (admin)
        Route::prefix('comissoes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminComissoesController::class, 'index']);
            Route::post('/{comissaoId}/marcar-paga', [\App\Http\Controllers\Admin\AdminComissoesController::class, 'marcarComoPaga'])
                ->where('comissaoId', '[0-9]+');
            Route::post('/criar-pagamento', [\App\Http\Controllers\Admin\AdminComissoesController::class, 'criarPagamento']);
            Route::get('/pagamentos', [\App\Http\Controllers\Admin\AdminComissoesController::class, 'pagamentos']);
        });

        // Logs de auditoria administrativa (central)
        Route::get('/audit-logs', [\App\Http\Controllers\Admin\AdminAuditLogController::class, 'index']);

        // Tickets de suporte (abertos pelos usuários do sistema)
        Route::get('/tickets', [\App\Http\Controllers\Admin\AdminSupportTicketController::class, 'index']);
        Route::get('/tickets/{id}', [\App\Http\Controllers\Admin\AdminSupportTicketController::class, 'show'])->where('id', '[0-9]+');
        Route::patch('/tickets/{id}', [\App\Http\Controllers\Admin\AdminSupportTicketController::class, 'update'])->where('id', '[0-9]+');
        Route::post('/tickets/{id}/responses', [\App\Http\Controllers\Admin\AdminSupportTicketController::class, 'storeResponse'])->where('id', '[0-9]+');

        // Gerenciamento de usuários admin do painel (tabela admin_users no banco central)
        Route::prefix('admin-users')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'show'])->where('id', '[0-9]+');
            Route::post('/', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'update'])->where('id', '[0-9]+');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'destroy'])->where('id', '[0-9]+');
        });

        // Upload de arquivos
        Route::prefix('upload')->group(function () {
            Route::post('/image', [\App\Http\Controllers\UploadController::class, 'uploadImage']);
            Route::delete('/image', [\App\Http\Controllers\UploadController::class, 'deleteImage']);
        });

        // Backups de Tenants
        Route::prefix('backups')->group(function () {
            Route::get('/tenants', [AdminBackupController::class, 'listarTenants']);
            Route::get('/listar', [AdminBackupController::class, 'listarBackups']);
            Route::post('/tenant/{tenantId}', [AdminBackupController::class, 'fazerBackup'])->where('tenantId', '[0-9]+');
            // 🔥 NOVO: Backup por empresa_id (filtra dados do banco central)
            Route::post('/empresa/{empresaId}', [AdminBackupController::class, 'fazerBackupEmpresa'])->where('empresaId', '[0-9]+');
            Route::get('/download/{filename}', [AdminBackupController::class, 'baixarBackup'])->where('filename', '[a-zA-Z0-9_.-]+');
            Route::delete('/{filename}', [AdminBackupController::class, 'deletarBackup'])->where('filename', '[a-zA-Z0-9_.-]+');
        });
        
        // 🔍 Exploração de banco de dados (estilo DBeaver - read-only)
        Route::prefix('db')->group(function () {
            Route::get('/tables', [AdminDatabaseController::class, 'listTables']);
            Route::get('/tables/{table}/columns', [AdminDatabaseController::class, 'listColumns'])->where('table', '[A-Za-z0-9_]+');
            Route::get('/tables/{table}/rows', [AdminDatabaseController::class, 'listRows'])->where('table', '[A-Za-z0-9_]+');
            Route::post('/tenants/{tenantId}/repair', [AdminDatabaseController::class, 'repairTenantSchema'])->where('tenantId', '[0-9]+');
        });
        
        // 🔥 NOVO: Gestão de tenants incompletos/abandonados
        Route::prefix('tenants-incompletos')->group(function () {
            Route::get('/', [AdminTenantsIncompletosController::class, 'index']);
            Route::delete('/{tenantId}', [AdminTenantsIncompletosController::class, 'destroy'])->where('tenantId', '[0-9]+');
            Route::post('/deletar-lote', [AdminTenantsIncompletosController::class, 'deletarLote']);
        });

        // 🔥 CROSS-TENANT: Gerenciamento de usuários em múltiplos tenants
        Route::prefix('cross-tenant')->group(function () {
            Route::post('/vincular', [AdminCrossTenantController::class, 'vincular']);
            Route::post('/desvincular', [AdminCrossTenantController::class, 'desvincular']);
            Route::get('/tenants-do-usuario', [AdminCrossTenantController::class, 'tenantsDoUsuario']);
        });
    });
});

// 🔁 Rotas de compatibilidade para bundles antigos do painel admin
// Isso garante que URLs antigas como /api/audit-logs e /api/admin-users continuem funcionando.
Route::middleware(['jwt.auth', 'auth.context', 'admin'])->group(function () {
    // Compat: /api/audit-logs -> mesma action de /api/admin/audit-logs
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AdminAuditLogController::class, 'index']);

    // Compat: /api/admin-users -> mesma action de /api/admin/admin-users
    Route::prefix('admin-users')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminAdminUserController::class, 'destroy'])->where('id', '[0-9]+');
    });
});

// 🆕 Rotas públicas para afiliados e onboarding
Route::prefix('v1')->group(function () {
    // Validar cupom de afiliado
    Route::post('/cupom/validar', [\App\Modules\Afiliado\Controllers\AfiliadoController::class, 'validarCupom']);
    
    // Rastreamento de referência de afiliado
    Route::prefix('afiliado-referencia')->group(function () {
        Route::post('/rastrear', [\App\Http\Controllers\Public\AfiliadoReferenciaController::class, 'rastrear']);
        Route::post('/verificar-cnpj', [\App\Http\Controllers\Public\AfiliadoReferenciaController::class, 'verificarCnpjJaUsouCupom']);
        Route::get('/buscar-ativa', [\App\Http\Controllers\Public\AfiliadoReferenciaController::class, 'buscarReferenciaAtiva']);
    });
    
    // Onboarding (rotas públicas - suportam autenticação opcional)
    // Se houver token, autentica o usuário. Caso contrário, funciona sem autenticação (usa user_id/session_id/email)
    Route::prefix('onboarding')->middleware(['auth.optional'])->group(function () {
        Route::post('/iniciar', [\App\Http\Controllers\Public\OnboardingController::class, 'iniciar']);
        Route::post('/marcar-etapa', [\App\Http\Controllers\Public\OnboardingController::class, 'marcarEtapa']);
        Route::post('/marcar-checklist', [\App\Http\Controllers\Public\OnboardingController::class, 'marcarChecklistItem']);
        Route::get('/verificar-status', [\App\Http\Controllers\Public\OnboardingController::class, 'verificarStatus']);
        Route::get('/progresso', [\App\Http\Controllers\Public\OnboardingController::class, 'buscarProgresso']);
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
