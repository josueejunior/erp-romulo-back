<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisService;
use Illuminate\Support\Facades\Cache;

class ClearRateLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:clear {identifier? : Identificador específico do rate limit (opcional)} {--force : Forçar limpeza sem confirmação}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpa o cache de rate limiting (Redis customizado e Laravel padrão)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $identifier = $this->argument('identifier');

        if (!$identifier) {
            $force = $this->option('force');
            
            if ($force || $this->confirm('Deseja limpar TODOS os rate limits? Isso pode afetar outros usuários.', true)) {
                $this->info('Limpando todos os rate limits...');
                RedisService::clearAllRateLimits();
                
                // Limpar também o cache do Laravel para garantir
                try {
                    Cache::flush();
                    $this->info('Cache do Laravel também foi limpo.');
                } catch (\Exception $e) {
                    $this->warn('Não foi possível limpar o cache do Laravel: ' . $e->getMessage());
                }
                
                $this->info('✅ Todos os rate limits foram limpos com sucesso!');
            } else {
                $this->info('Operação cancelada.');
            }
        } else {
            RedisService::clearRateLimit($identifier);
            $this->info("✅ Rate limit para '{$identifier}' foi limpo com sucesso!");
        }

        return 0;
    }
}
