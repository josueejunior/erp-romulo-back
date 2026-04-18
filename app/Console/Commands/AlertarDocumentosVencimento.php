<?php

namespace App\Console\Commands;

use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AlertarDocumentosVencimento extends Command
{
    protected $signature = 'documentos:vencimento {--empresa_id=} {--dias=30}';

    protected $description = 'Lista documentos de habilitacao vencendo ou vencidos e registra em log';

    public function handle(): int
    {
        $empresaIdOption = $this->option('empresa_id');
        $dias = (int) $this->option('dias') ?: 30;

        $empresas = $empresaIdOption
            ? Empresa::where('id', $empresaIdOption)->get()
            : Empresa::all();

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa encontrada.');
            return self::SUCCESS;
        }

        /** @var DocumentoHabilitacaoRepositoryInterface $repo */
        $repo = app(DocumentoHabilitacaoRepositoryInterface::class);

        foreach ($empresas as $empresa) {
            // Configurar contexto de empresa para escopos globais que dependem de current_empresa_id
            app()->instance('current_empresa_id', $empresa->id);

            $vencendo = $repo->buscarVencendo($empresa->id, $dias);
            $vencidos = $repo->buscarVencidos($empresa->id);

            $this->line("Empresa {$empresa->id} - {$empresa->razao_social}");
            $this->line("  Vencendo (<= {$dias} dias): " . count($vencendo));
            $this->line("  Vencidos: " . count($vencidos));

            Log::info('documentos:vencimento', [
                'empresa_id' => $empresa->id,
                'vencendo_count' => count($vencendo),
                'vencidos_count' => count($vencidos),
                'dias' => $dias,
                'vencendo_ids' => array_map(fn($d) => $d->id, $vencendo),
                'vencidos_ids' => array_map(fn($d) => $d->id, $vencidos),
            ]);
        }

        $this->info('Varredura concluida.');

        return self::SUCCESS;
    }
}
