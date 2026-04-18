<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Recalcular financeiros do processo específico
$processo = \App\Modules\Processo\Models\Processo::where('numero_modalidade', 'LIKE', '%097/2025%')->first();

if ($processo) {
    echo "Processo encontrado: {$processo->id} - Status: {$processo->status}\n";
    
    // Forçar recálculo dos itens
    $saldoService = app(\App\Modules\Processo\Services\SaldoService::class);
    $resultado = $saldoService->recalcularValoresFinanceirosItens($processo);
    echo "Recálculo concluído. Itens atualizados: {$resultado['itens_atualizados']}\n";
    
    // Verificar Lucro Bruto do primeiro item
    $item = $processo->itens()->first();
    echo "Item #{$item->numero_item} - Valor Vencido: {$item->valor_vencido} - Custo Estimado: {$item->getCustoTotal()} - Lucro Bruto: {$item->lucro_bruto}\n";

    // 2. Verificar contador do repositório
    $repo = app(\App\Domain\Processo\Repositories\ProcessoRepositoryInterface::class);
    // Simular filtro usado no controller
    $filtro = [
        'empresa_id' => $processo->empresa_id,
        'status' => 'execucao',
        'per_page' => 1
    ];
    
    // Definir tenant/empresa contextual
    if (function_exists('tenancy')) {
        // Tentar definir tenant se necessário, mas em script standalone pode ser tricky
        // Vamos assumir single tenant ou que repository lida com isso se passarmos empresa_id
    }

    try {
        $count = $repo->buscarComFiltros($filtro)->total();
        echo "Contagem via Repository (status=execucao): {$count}\n";
        
        // Verificar status reverso
        $statusNoBanco = \DB::table('processos')->where('id', $processo->id)->value('status');
        echo "Status cru no banco de dados: {$statusNoBanco}\n";
        
    } catch (\Exception $e) {
        echo "Erro ao contar: " . $e->getMessage() . "\n";
    }

} else {
    echo "Processo 097/2025 não encontrado.\n";
}
