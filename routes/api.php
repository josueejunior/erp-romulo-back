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
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware(['throttle:5,1', 'throttle:10,60']); // 5/min, 10/hora (prevenir spam)
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware(['throttle:5,1', 'throttle:10,60']); // 5/min, 10/hora
    
    // Upload público (para cadastro público)
    Route::post('/upload/image', [\App\Http\Controllers\UploadController::class, 'uploadImage'])
        ->middleware(['throttle:10,1']); // 10 uploads por minuto
    
    // Cadastro público (cria tenant + assinatura + usuário)
    Route::post('/cadastro-publico', [\App\Http\Controllers\Public\CadastroPublicoController::class, 'store'])
        ->middleware(['throttle:5,1', 'throttle:10,60']); // 5/min, 10/hora
    
    // Consulta pública de CNPJ (para cadastro público)
    Route::get('/cadastro-publico/consultar-cnpj/{cnpj}', [\App\Http\Controllers\Public\CadastroPublicoController::class, 'consultarCnpj'])
        ->middleware(['throttle:10,1']); // 10 consultas por minuto

    // Rotas autenticadas
    // Rate limiting: 120 requisições por minuto, 1000 por hora
    // Rotas de criação/edição têm rate limiting adicional
    // Rotas autenticadas: aqui sim aplicamos contexto (empresa/tenant) após auth
    // 🔥 JWT STATELESS: Middleware unificado com autenticação JWT
    // Consolida JWT auth + SetAuthContext + EnsureEmpresaAtivaContext em um único middleware
    // Sem estado, sem sessão, sem Redis - perfeito para escalabilidade horizontal
    Route::middleware([\App\Http\Middleware\AuthenticateAndBootstrap::class, 'throttle:120,1'])->group(function () {
        // Rotas que NÃO precisam de assinatura (exceções)
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);
        
        // Rota de compatibilidade para /user/roles (redireciona para /users/roles)
        Route::get('/user/roles', [\App\Modules\Auth\Controllers\FixUserRolesController::class, 'getCurrentUserRoles']);
        
        // Obter roles do usuário atual (precisa estar fora do CheckSubscription)
        Route::get('/users/roles', [\App\Modules\Auth\Controllers\FixUserRolesController::class, 'getCurrentUserRoles']);
        
        // Trocar empresa ativa (precisa estar fora do CheckSubscription para permitir troca mesmo sem assinatura)
        Route::put('/users/empresa-ativa', [ApiUserController::class, 'switchEmpresaAtiva']);
        
        // Assinaturas e Pagamentos (não precisam de verificação de assinatura)
        Route::prefix('assinaturas')->group(function () {
            Route::get('/atual', [ApiAssinaturaController::class, 'atual']);
            Route::get('/status', [ApiAssinaturaController::class, 'status']);
            Route::get('/', [ApiAssinaturaController::class, 'index']);
            Route::post('/', [ApiAssinaturaController::class, 'store']);
            Route::post('/trocar-plano', [ApiAssinaturaController::class, 'trocarPlano']);
            Route::post('/{assinatura}/renovar', [ApiAssinaturaController::class, 'renovar']);
            Route::post('/{assinatura}/cancelar', [ApiAssinaturaController::class, 'cancelar']);
        });

        // Cupons
        Route::get('/cupons/{codigo}/validar', [\App\Modules\Assinatura\Controllers\CupomController::class, 'validar']);

        Route::prefix('payments')->group(function () {
            Route::post('/processar-assinatura', [ApiPaymentController::class, 'processarAssinatura']);
        });
        
        // Notificações (não precisa de assinatura ativa)
        Route::get('/notifications', [\App\Modules\Notification\Controllers\NotificationController::class, 'index']);
        
        // Rotas que PRECISAM de assinatura ativa
        Route::middleware([\App\Http\Middleware\CheckSubscription::class])->group(function () {
            // Dashboard
            Route::get('/dashboard', [ApiDashboardController::class, 'index']);
            
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
                Route::post('/marcar-perdido', [ApiProcessoController::class, 'marcarPerdido']);
                Route::post('/confirmar-pagamento', [ApiProcessoController::class, 'confirmarPagamento']);
                Route::get('/confirmacoes-pagamento', [ApiProcessoController::class, 'historicoConfirmacoes']);
                Route::get('/sugerir-status', [ApiProcessoController::class, 'sugerirStatus']);
                Route::get('/ficha-export', [ApiProcessoController::class, 'fichaTecnicaExport']);
                Route::get('/download-edital', [ApiProcessoController::class, 'downloadEdital']);
                
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
            Route::module('orgaos', ApiOrgaoController::class, 'orgao');
            
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
                Route::get('/orcamentos', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'index']);
                Route::get('/orcamentos/export', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'export']);
                Route::get('/orcamentos/por-fornecedor', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'porFornecedor']);
                Route::get('/orcamentos/por-status', [\App\Modules\Orcamento\Controllers\RelatorioController::class, 'porStatus']);
            });
        }); // Fim do grupo com CheckSubscription
    });

    // Webhooks (públicos, sem autenticação)
    Route::prefix('webhooks')->group(function () {
        Route::post('/mercadopago', [ApiWebhookController::class, 'mercadopago']);
    });
});

// Rotas do Painel Admin Central (fora do tenant e fora do v1)
Route::prefix('admin')->group(function () {
    // Autenticação admin - Rate limiting mais restritivo (3/min, 5/hora)
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware(['throttle:3,1', 'throttle:5,60']);
    
    // 🔥 JWT STATELESS: Rotas admin - usar AuthenticateAndBootstrap (JWT)
    Route::middleware([\App\Http\Middleware\AuthenticateAndBootstrap::class, 'admin'])->group(function () {
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
            Route::get('/tenants', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'tenants']);
            Route::get('/{tenantId}/{assinaturaId}', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'show'])
                ->where(['tenantId' => '[0-9]+', 'assinaturaId' => '[0-9]+']);
            Route::put('/{tenantId}/{assinaturaId}', [\App\Http\Controllers\Admin\AdminAssinaturaController::class, 'update'])
                ->where(['tenantId' => '[0-9]+', 'assinaturaId' => '[0-9]+']);
        });

        // Gerenciamento de cupons
        Route::prefix('cupons')->group(function () {
            Route::get('/', [\App\Modules\Assinatura\Controllers\CupomController::class, 'index']);
            Route::post('/', [\App\Modules\Assinatura\Controllers\CupomController::class, 'store']);
            Route::put('/{cupom}', [\App\Modules\Assinatura\Controllers\CupomController::class, 'update']);
            Route::delete('/{cupom}', [\App\Modules\Assinatura\Controllers\CupomController::class, 'destroy']);
        });

        // Upload de arquivos
        Route::prefix('upload')->group(function () {
            Route::post('/image', [\App\Http\Controllers\UploadController::class, 'uploadImage']);
            Route::delete('/image', [\App\Http\Controllers\UploadController::class, 'deleteImage']);
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
