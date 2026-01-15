<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ClearRateLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:clear {identifier? : Identificador específico do rate limit ou IP (opcional)} {--force : Forçar limpeza sem confirmação} {--ip= : Limpar rate limits de um IP específico} {--endpoint= : Limpar rate limits de um endpoint específico}';

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
        $ip = $this->option('ip');
        $endpoint = $this->option('endpoint');
        $force = $this->option('force');

        // Limpar por IP específico
        if ($ip) {
            $this->clearByIp($ip, $endpoint);
            return 0;
        }

        // Limpar por endpoint específico
        if ($endpoint) {
            $this->clearByEndpoint($endpoint);
            return 0;
        }

        // Limpar todos os rate limits
        if (!$identifier) {
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

    /**
     * Limpar rate limits de um IP específico
     */
    private function clearByIp(string $ip, ?string $endpoint = null): void
    {
        $this->info("Limpando rate limits para IP: {$ip}" . ($endpoint ? " no endpoint: {$endpoint}" : ''));
        
        try {
            $patterns = [
                "rate_limit:{$ip}:*",
                "laravel_cache:illuminate_rate_limit:{$ip}:*",
                "laravel_cache:throttle:{$ip}:*",
            ];
            
            if ($endpoint) {
                $patterns = array_merge($patterns, [
                    "rate_limit:{$ip}:*:{$endpoint}",
                    "rate_limit:{$ip}:*:api/v1/{$endpoint}",
                ]);
            }
            
            $cleared = 0;
            foreach ($patterns as $pattern) {
                try {
                    $keys = Redis::keys($pattern);
                    if (!empty($keys)) {
                        Redis::del($keys);
                        $cleared += count($keys);
                    }
                } catch (\Exception $e) {
                    // Ignorar erros
                }
            }
            
            $this->info("✅ Limpeza concluída. {$cleared} chave(s) removida(s).");
        } catch (\Exception $e) {
            $this->error("❌ Erro ao limpar rate limits: " . $e->getMessage());
        }
    }

    /**
     * Limpar rate limits de um endpoint específico
     */
    private function clearByEndpoint(string $endpoint): void
    {
        $this->info("Limpando rate limits para endpoint: {$endpoint}");
        
        try {
            $patterns = [
                "rate_limit:*:*:{$endpoint}",
                "rate_limit:*:*:api/v1/{$endpoint}",
            ];
            
            $cleared = 0;
            foreach ($patterns as $pattern) {
                try {
                    $keys = Redis::keys($pattern);
                    if (!empty($keys)) {
                        Redis::del($keys);
                        $cleared += count($keys);
                    }
                } catch (\Exception $e) {
                    // Ignorar erros
                }
            }
            
            $this->info("✅ Limpeza concluída. {$cleared} chave(s) removida(s).");
        } catch (\Exception $e) {
            $this->error("❌ Erro ao limpar rate limits: " . $e->getMessage());
        }
    }
}
