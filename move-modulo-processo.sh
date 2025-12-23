#!/bin/bash

# Script para mover módulo Processo (piloto)
# Execute no WSL: bash move-modulo-processo.sh

cd "$(dirname "$0")/app"

echo "🚀 Movendo módulo Processo..."

# Models
echo "  📦 Movendo Models..."
mv Models/Processo.php Modules/Processo/Models/ 2>/dev/null
mv Models/ProcessoItem.php Modules/Processo/Models/ 2>/dev/null
mv Models/ProcessoDocumento.php Modules/Processo/Models/ 2>/dev/null
mv Models/ProcessoItemVinculo.php Modules/Processo/Models/ 2>/dev/null

# Services
echo "  🔧 Movendo Services..."
mv Services/ProcessoStatusService.php Modules/Processo/Services/ 2>/dev/null
mv Services/ProcessoValidationService.php Modules/Processo/Services/ 2>/dev/null
mv Services/SaldoService.php Modules/Processo/Services/ 2>/dev/null
mv Services/DisputaService.php Modules/Processo/Services/ 2>/dev/null
mv Services/ExportacaoService.php Modules/Processo/Services/ 2>/dev/null

# Controllers
echo "  🎮 Movendo Controllers..."
mv Http/Controllers/Api/ProcessoController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/Api/ProcessoItemController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/Api/DisputaController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/Api/JulgamentoController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/Api/SaldoController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/Api/ExportacaoController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/ProcessoController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/ProcessoItemController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/DisputaController.php Modules/Processo/Controllers/ 2>/dev/null
mv Http/Controllers/JulgamentoController.php Modules/Processo/Controllers/ 2>/dev/null

# Resources
echo "  📄 Movendo Resources..."
mv Http/Resources/ProcessoResource.php Modules/Processo/Resources/ 2>/dev/null
mv Http/Resources/ProcessoListResource.php Modules/Processo/Resources/ 2>/dev/null
mv Http/Resources/ProcessoItemResource.php Modules/Processo/Resources/ 2>/dev/null

# Observers
echo "  👁️ Movendo Observers..."
mv Observers/ProcessoObserver.php Modules/Processo/Observers/ 2>/dev/null

# Policies
echo "  🔒 Movendo Policies..."
mv Policies/ProcessoPolicy.php Modules/Processo/Policies/ 2>/dev/null

echo "✅ Módulo Processo movido!"
echo ""
echo "⚠️  IMPORTANTE: Agora é necessário atualizar:"
echo "  1. Namespaces nos arquivos movidos"
echo "  2. Imports em todos os arquivos que referenciam Processo"
echo "  3. Rotas em routes/api.php"
echo "  4. Service Providers (AppServiceProvider)"
echo "  5. Composer autoload (se necessário)"

