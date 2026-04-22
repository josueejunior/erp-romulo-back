<?php

namespace App\Application\ProcessoDocumento\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Facades\Storage;

/**
 * Use Case: Baixar arquivo de documento
 */
class BaixarArquivoDocumentoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoDocumentoRepositoryInterface $processoDocumentoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $processoId
     * @param int $empresaId
     * @param int $processoDocumentoId
     * @return array|null ['path' => string, 'nome' => string, 'mime' => string]
     */
    public function executar(int $processoId, int $empresaId, int $processoDocumentoId): ?array
    {
        // Buscar processo
        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo || $processo->empresa_id !== $empresaId) {
            throw new NotFoundException('Processo não encontrado ou não pertence à empresa.');
        }

        // Buscar documento
        $procDoc = $this->processoDocumentoRepository->buscarModeloPorId($processoDocumentoId);
        if (!$procDoc || $procDoc->processo_id !== $processoId || $procDoc->empresa_id !== $empresaId) {
            throw new NotFoundException('Documento do processo não encontrado.');
        }

        $path = $procDoc->caminho_arquivo;
        if (!$path) {
            return null;
        }

        // Caminho padrão no disco tenant-aware atual.
        if (Storage::disk('public')->exists($path)) {
            return [
                'path' => $path,
                'nome' => $procDoc->nome_arquivo ?? basename($path),
                'mime' => $procDoc->mime ?? 'application/octet-stream',
            ];
        }

        // Compatibilidade legada: alguns arquivos antigos ficaram no storage
        // global (não sufixado por tenant).
        $legacyAbsolutePath = base_path('storage/app/public/' . ltrim($path, '/'));
        if (is_file($legacyAbsolutePath)) {
            return [
                'path' => $path,
                'nome' => $procDoc->nome_arquivo ?? basename($path),
                'mime' => $procDoc->mime ?? 'application/octet-stream',
                'legacy_absolute_path' => $legacyAbsolutePath,
            ];
        }
        return null;
    }
}

