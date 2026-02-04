<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cnpj = '64.051.697/0001-02';
$cnpjClean = preg_replace('/\D/', '', $cnpj);

echo "Buscando empresa CNPJ: $cnpj / $cnpjClean\n";

$empresa = \App\Models\Empresa::where('cnpj', $cnpjClean)->orWhere('cnpj', $cnpj)->first();

if (!$empresa) {
    echo "Empresa não encontrada.\n";
    
    // Tentar buscar por qualquer assinatura para ver se banco está acessível
    $count = \App\Modules\Assinatura\Models\Assinatura::count();
    echo "Total de assinaturas no banco: $count\n";
    exit;
}

echo "Empresa encontrada: {$empresa->razao_social} (ID: {$empresa->id})\n";

$assinatura = \App\Modules\Assinatura\Models\Assinatura::where('empresa_id', $empresa->id)->latest()->first();

if (!$assinatura) {
    echo "Assinatura não encontrada para empresa {$empresa->id}.\n";
    exit;
}

echo "\n--- Detalhes da Assinatura ---\n";
echo "ID: {$assinatura->id}\n";
echo "Status no Banco: {$assinatura->status}\n";
echo "Data Início: " . ($assinatura->data_inicio ? $assinatura->data_inicio->format('Y-m-d') : 'N/A') . "\n";
echo "Data Fim: " . ($assinatura->data_fim ? $assinatura->data_fim->format('Y-m-d') : 'N/A') . "\n";
echo "Plano ID: {$assinatura->plano_id}\n";
echo "------------------------------\n";
echo "Método isAtiva(): " . ($assinatura->isAtiva() ? 'TRUE' : 'FALSE') . "\n";
echo "Método isExpirada(): " . ($assinatura->isExpirada() ? 'TRUE' : 'FALSE') . "\n";
