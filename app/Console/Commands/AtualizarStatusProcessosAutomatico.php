<?php

namespace App\Console\Commands;

use App\Modules\Processo\Services\ProcessoStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AtualizarStatusProcessosAutomatico extends Command
{
    protected $signature = 'processos:atualizar-status-automatico {--empresa= : ID da empresa (opcional)}';
    protected $description = 'Atualiza status de processos pelo período: em preparação → em disputa → em julgamento (intervalo início/fim da disputa)';

    public function __construct(
        private ProcessoStatusService $statusService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $empresaId = $this->option('empresa') ? (int) $this->option('empresa') : null;
            $resultado = $this->statusService->verificarEAtualizarStatusAutomaticos($empresaId);

            foreach ($resultado['erros'] as $msg) {
                $this->error($msg);
            }
            $this->info("Processos atualizados pelo período: {$resultado['atualizados']}");
            if ($resultado['sugeridos'] > 0) {
                $this->line("Sugestões de status perdido: {$resultado['sugeridos']}");
            }

            Log::info('processos:atualizar-status-automatico', [
                'atualizados' => $resultado['atualizados'],
                'sugeridos' => $resultado['sugeridos'],
                'erros' => count($resultado['erros']),
                'empresa_id' => $empresaId,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            Log::error('processos:atualizar-status-automatico erro', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
