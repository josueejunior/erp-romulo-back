<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisService;

class ClearRateLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:clear {identifier? : Identificador específico do rate limit (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpa o cache de rate limiting do Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $identifier = $this->argument('identifier');

        if (!$identifier) {
            if ($this->confirm('Deseja limpar TODOS os rate limits? Isso pode afetar outros usuários.')) {
                RedisService::clearAllRateLimits();
                $this->info('Todos os rate limits foram limpos com sucesso!');
            } else {
                $this->info('Operação cancelada.');
            }
        } else {
            RedisService::clearRateLimit($identifier);
            $this->info("Rate limit para '{$identifier}' foi limpo com sucesso!");
        }

        return 0;
    }
}
