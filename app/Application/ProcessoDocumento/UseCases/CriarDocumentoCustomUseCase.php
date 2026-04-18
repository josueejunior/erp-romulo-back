<?php

namespace App\Application\ProcessoDocumento\UseCases;

use App\Application\ProcessoDocumento\DTOs\CriarDocumentoCustomDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Use Case: Criar documento customizado
 */
class CriarDocumentoCustomUseCase
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    ];

    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoDocumentoRepositoryInterface $processoDocumentoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $processoId
     * @param int $empresaId
     * @param CriarDocumentoCustomDTO $dto
     * @param UploadedFile|null $arquivo
     * @return \App\Modules\Processo\Models\ProcessoDocumento
     */
    public function executar(
        int $processoId,
        int $empresaId,
        CriarDocumentoCustomDTO $dto,
        ?UploadedFile $arquivo = null
    ) {
        // Buscar processo
        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo || $processo->empresa_id !== $empresaId) {
            throw new NotFoundException('Processo não encontrado ou não pertence à empresa.');
        }

        $dados = [
            'empresa_id' => $empresaId,
            'processo_id' => $processoId,
            'documento_habilitacao_id' => null,
            'documento_custom' => true,
            'titulo_custom' => $dto->tituloCustom,
            'exigido' => $dto->exigido,
            'disponivel_envio' => $dto->disponivelEnvio,
            'status' => $dto->status,
            'observacoes' => $dto->observacoes,
        ];

        // Processar arquivo se enviado
        if ($arquivo instanceof UploadedFile) {
            $this->validarArquivo($arquivo);

            $fileName = time() . '_' . $arquivo->getClientOriginalName();
            $path = $arquivo->storeAs("processos/{$processoId}/documentos", $fileName, 'public');
            
            $dados = array_merge($dados, [
                'nome_arquivo' => $fileName,
                'caminho_arquivo' => $path,
                'mime' => $arquivo->getMimeType(),
                'tamanho_bytes' => $arquivo->getSize(),
                'status' => 'anexado',
            ]);
        }

        return $this->processoDocumentoRepository->criar($dados);
    }

    /**
     * Validar arquivo
     */
    private function validarArquivo(UploadedFile $arquivo): void
    {
        if ($arquivo->getSize() > self::MAX_FILE_SIZE) {
            throw new DomainException('Arquivo muito grande. Tamanho máximo permitido: 10MB');
        }

        if (!in_array($arquivo->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new DomainException('Tipo de arquivo não permitido. Apenas PDF, imagens (JPG/PNG) e documentos Office são aceitos.');
        }
    }
}

