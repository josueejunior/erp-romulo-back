<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProcessoStatusService;

class AtualizarStatusProcessos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'processos:atualizar-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza automaticamente os status dos processos baseado nas regras de negócio';

    /**
     * Execute the console command.
     */
    public function handle(ProcessoStatusService $service)
    {
        $this->info('Verificando processos para atualização automática de status...');

        $resultado = $service->verificarEAtualizarStatusAutomaticos();

        $this->info("Processos atualizados: {$resultado['atualizados']}");
        $this->info("Processos com sugestão de mudança: {$resultado['sugeridos']}");

        if (!empty($resultado['erros'])) {
            $this->error('Erros encontrados:');
            foreach ($resultado['erros'] as $erro) {
                $this->error("  - {$erro}");
            }
        }

        $this->info('Verificação concluída!');
    }
}


