<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AuditApiRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:audit-api-routes {--file=frontend_endpoints.txt : File containing frontend endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit API routes against frontend service expectations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = base_path($this->option('file'));
        
        if (!file_exists($filePath)) {
            $this->error("Frontend endpoints file not found: {$filePath}");
            $this->info("Create it with: grep -rhE \"(api|http)\.(get|post|put|patch|delete)\(['\\\"]([^'\\\"]+)['\\\"]\" src/services/ | grep -oE \"['\\\"]([^'\\\"]+)['\\\"]\" | sed \"s/['\\\"]//g\" | sort | uniq > frontend_endpoints.txt");
            return 1;
        }

        $frontendEndpoints = array_filter(array_map('trim', explode("\n", file_get_contents($filePath))));
        
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
            ];
        });

        $this->info("Auditing " . count($frontendEndpoints) . " frontend endpoints against " . $routes->count() . " backend routes...");
        
        $missing = [];
        $found = [];
        $mismatched = []; // Could be used for case sensitivity or minor differences

        foreach ($frontendEndpoints as $endpoint) {
            $cleanEndpoint = ltrim($endpoint, '/');
            
            // Search for matches
            $match = $routes->first(function ($route) use ($cleanEndpoint) {
                $uri = $route['uri'];
                
                // 1. Exact match (including api/v1)
                if ($uri === $cleanEndpoint || $uri === 'api/v1/' . $cleanEndpoint) {
                    return true;
                }

                // 2. Base match (ignoring parameters)
                $uriClean = preg_replace('/^api\/v1\//', '', $uri);
                $uriBase = preg_replace('/\{[^}]+\}/', '', $uriClean);
                $uriBase = rtrim($uriBase, '/');
                
                if ($uriBase !== '' && ($cleanEndpoint === $uriBase || str_starts_with($cleanEndpoint, $uriBase . '/'))) {
                    return true;
                }

                return false;
            });

            if ($match) {
                $found[] = [
                    'endpoint' => $endpoint,
                    'backend' => $match['uri'],
                    'methods' => implode('|', array_filter($match['methods'], fn($m) => $m !== 'HEAD'))
                ];
            } else {
                $missing[] = ['endpoint' => $endpoint];
            }
        }

        $this->info("\n✅ FOUND ROUTES:");
        $this->table(['Frontend Endpoint', 'Backend URI', 'Methods'], $found);
        
        if (count($missing) > 0) {
            $this->error("\n❌ MISSING ROUTES (404 RISK):");
            $this->table(['Missing Endpoint'], $missing);
            
            $this->line("\n💡 Suggestions:");
            foreach ($missing as $m) {
                $e = $m['endpoint'];
                if ($e === '/oportunidades') {
                    $this->line("   - /oportunidades: Rota não encontrada. Verifique se o módulo de Oportunidades foi implementado no backend.");
                } elseif (str_contains($e, 'notificacoes')) {
                    $this->line("   - $e: Verifique se deve ser /notifications (existente no backend).");
                } elseif ($e === '/processos-resumo') {
                    $this->line("   - /processos-resumo: Verifique se deve ser /processos/resumo.");
                }
            }
        } else {
            $this->info("\n✨ All audited frontend routes have a corresponding backend endpoint!");
        }

        return count($missing) === 0 ? 0 : 1;
    }
}
