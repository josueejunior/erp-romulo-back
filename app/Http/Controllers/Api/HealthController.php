<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Controller para health check do sistema
 * 
 * Usado para monitoramento e verificação de saúde do sistema
 */
class HealthController extends Controller
{
    /**
     * Health check básico
     * Retorna 200 se sistema está OK, 503 se houver problemas
     */
    public function check(): JsonResponse
    {
        try {
            // Verificação básica de conexão com banco
            DB::connection()->getPdo();
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error';
            Log::error('Health check - Database connection failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $status = $dbStatus === 'ok' ? 200 : 503;

        return response()->json([
            'status' => $dbStatus === 'ok' ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $dbStatus,
            ],
        ], $status);
    }

    /**
     * Health check detalhado
     * Verifica todos os componentes do sistema
     */
    public function detailed(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // 1. Database Connection
        try {
            DB::connection()->getPdo();
            $dbPing = DB::select('SELECT 1 as ping')[0]->ping ?? null;
            $checks['database'] = [
                'status' => $dbPing ? 'ok' : 'error',
                'message' => $dbPing ? 'Connected' : 'Connection failed',
            ];
            if (!$dbPing) $allHealthy = false;
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $allHealthy = false;
        }

        // 2. Redis Connection
        try {
            if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
                Redis::connection()->ping();
                $checks['redis'] = [
                    'status' => 'ok',
                    'message' => 'Connected',
                ];
            } else {
                $checks['redis'] = [
                    'status' => 'skipped',
                    'message' => 'Redis not configured',
                ];
            }
        } catch (\Exception $e) {
            $checks['redis'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            // Redis não é crítico, então não marca como unhealthy
        }

        // 3. Cache
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            $checks['cache'] = [
                'status' => $value === 'test' ? 'ok' : 'error',
                'message' => $value === 'test' ? 'Working' : 'Read/Write failed',
            ];
            if ($value !== 'test') $allHealthy = false;
        } catch (\Exception $e) {
            $checks['cache'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $allHealthy = false;
        }

        // 4. Storage (disco)
        try {
            $testPath = storage_path('app/health_check_test.txt');
            file_put_contents($testPath, 'test');
            $content = file_get_contents($testPath);
            unlink($testPath);
            $checks['storage'] = [
                'status' => $content === 'test' ? 'ok' : 'error',
                'message' => $content === 'test' ? 'Writable' : 'Write failed',
            ];
            if ($content !== 'test') $allHealthy = false;
        } catch (\Exception $e) {
            $checks['storage'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $allHealthy = false;
        }

        // 5. Memory Usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryPercent = $this->getMemoryPercent($memoryUsage, $memoryLimit);
        $checks['memory'] = [
            'status' => $memoryPercent < 90 ? 'ok' : 'warning',
            'message' => "Usage: {$memoryPercent}% ({$this->formatBytes($memoryUsage)} / {$memoryLimit})",
            'usage_bytes' => $memoryUsage,
            'limit' => $memoryLimit,
            'percent' => $memoryPercent,
        ];
        if ($memoryPercent >= 95) $allHealthy = false;

        // 6. Queue (se configurado)
        try {
            if (config('queue.default') !== 'sync') {
                // Verificar se há muitos jobs falhados
                $failedJobsCount = DB::table('failed_jobs')->count();
                $checks['queue'] = [
                    'status' => $failedJobsCount < 100 ? 'ok' : 'warning',
                    'message' => "Failed jobs: {$failedJobsCount}",
                    'failed_jobs' => $failedJobsCount,
                ];
                if ($failedJobsCount >= 500) $allHealthy = false;
            } else {
                $checks['queue'] = [
                    'status' => 'skipped',
                    'message' => 'Sync queue (no async processing)',
                ];
            }
        } catch (\Exception $e) {
            $checks['queue'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        $status = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
        ], $status);
    }

    /**
     * Calcula porcentagem de uso de memória
     */
    private function getMemoryPercent(int $usage, string $limit): float
    {
        $limitBytes = $this->convertToBytes($limit);
        if ($limitBytes <= 0) {
            return 0;
        }
        return round(($usage / $limitBytes) * 100, 2);
    }

    /**
     * Converte string de limite de memória para bytes
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Formata bytes para formato legível
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}



