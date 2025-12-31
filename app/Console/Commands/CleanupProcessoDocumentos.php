<?php

namespace App\Console\Commands;

use App\Modules\Processo\Models\ProcessoDocumento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CleanupProcessoDocumentos extends Command
{
    protected $signature = 'documentos:cleanup-processos {--days=7 : Excluir arquivos nao referenciados com mais de N dias}';

    protected $description = 'Remove arquivos de documentos de processos que nao estao mais referenciados no banco';

    public function handle(): int
    {
        $dias = (int) $this->option('days') ?: 7;
        $limiteTimestamp = now()->subDays($dias)->timestamp;

        $disk = Storage::disk('public');
        $referenciados = ProcessoDocumento::query()
            ->whereNotNull('caminho_arquivo')
            ->pluck('caminho_arquivo')
            ->filter()
            ->values()
            ->all();

        $referenciadosMap = array_flip($referenciados);

        $todosArquivos = $disk->allFiles('processos');

        $removidos = 0;
        $ignorados = 0;

        foreach ($todosArquivos as $path) {
            if (isset($referenciadosMap[$path])) {
                $ignorados++;
                continue;
            }

            $mod = $disk->lastModified($path);
            if ($mod !== false && $mod > $limiteTimestamp) {
                $ignorados++;
                continue; // recente, manter
            }

            if ($disk->delete($path)) {
                $removidos++;
                $this->line("Removido: {$path}");
            }
        }

        $msg = "Arquivos removidos: {$removidos}; ignorados: {$ignorados}; dias limite: {$dias}";
        $this->info($msg);

        Log::info('documentos:cleanup-processos', [
            'removidos' => $removidos,
            'ignorados' => $ignorados,
            'dias' => $dias,
        ]);

        return self::SUCCESS;
    }
}
