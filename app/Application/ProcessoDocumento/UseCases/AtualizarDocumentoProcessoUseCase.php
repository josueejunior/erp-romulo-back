<?php

namespace App\Application\ProcessoDocumento\UseCases;

use App\Application\ProcessoDocumento\DTOs\AtualizarDocumentoProcessoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Documento\Models\DocumentoHabilitacaoVersao;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Use Case: Atualizar documento de processo
 */
class AtualizarDocumentoProcessoUseCase
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
        private DocumentoHabilitacaoRepositoryInterface $documentoHabilitacaoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $processoId
     * @param int $empresaId
     * @param int $processoDocumentoId
     * @param AtualizarDocumentoProcessoDTO $dto
     * @param UploadedFile|null $arquivo
     * @return \App\Modules\Processo\Models\ProcessoDocumento
     */
    public function executar(
        int $processoId,
        int $empresaId,
        int $processoDocumentoId,
        AtualizarDocumentoProcessoDTO $dto,
        ?UploadedFile $arquivo = null
    ) {
        // Buscar processo
        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo || $processo->empresa_id !== $empresaId) {
            throw new NotFoundException('Processo não encontrado ou não pertence à empresa.');
        }

        // Buscar documento
        $procDoc = $this->processoDocumentoRepository->buscarModeloPorId($processoDocumentoId);
        if (!$procDoc || $procDoc->processo_id !== $processoId || $procDoc->empresa_id !== $empresaId) {
            throw new NotFoundException('Documento não encontrado ou não pertence ao processo.');
        }

        // Validar versão se enviada
        if ($dto->versaoDocumentoHabilitacaoId !== null) {
            if (!$procDoc->documento_habilitacao_id) {
                throw new DomainException('Documentos customizados não podem ter versão.');
            }

            $versao = DocumentoHabilitacaoVersao::where('id', $dto->versaoDocumentoHabilitacaoId)
                ->where('documento_habilitacao_id', $procDoc->documento_habilitacao_id)
                ->first();

            if (!$versao) {
                throw new NotFoundException('Versão do documento não encontrada.');
            }
        }

        // Validar e processar arquivo se enviado
        $dadosAtualizacao = [];
        
        if ($arquivo instanceof UploadedFile) {
            $this->validarArquivo($arquivo);
            
            // Remover arquivo antigo se existir
            if ($procDoc->caminho_arquivo) {
                Storage::disk('public')->delete($procDoc->caminho_arquivo);
            }

            $fileName = time() . '_' . $arquivo->getClientOriginalName();
            $path = $arquivo->storeAs("processos/{$processoId}/documentos", $fileName, 'public');
            
            $dadosAtualizacao = array_merge($dadosAtualizacao, [
                'nome_arquivo' => $fileName,
                'caminho_arquivo' => $path,
                'mime' => $arquivo->getMimeType(),
                'tamanho_bytes' => $arquivo->getSize(),
                'status' => 'anexado',
            ]);
        }

        // Adicionar dados do DTO
        if ($dto->exigido !== null) {
            $dadosAtualizacao['exigido'] = $dto->exigido;
        }
        if ($dto->disponivelEnvio !== null) {
            $dadosAtualizacao['disponivel_envio'] = $dto->disponivelEnvio;
        }
        if ($dto->status !== null) {
            $dadosAtualizacao['status'] = $dto->status;
        }
        if ($dto->observacoes !== null) {
            $dadosAtualizacao['observacoes'] = $dto->observacoes;
        }
        if ($dto->versaoDocumentoHabilitacaoId !== null) {
            $dadosAtualizacao['versao_documento_habilitacao_id'] = $dto->versaoDocumentoHabilitacaoId;
        }

        // Atualizar documento
        $this->processoDocumentoRepository->atualizar($procDoc, $dadosAtualizacao);

        return $procDoc->fresh(['versaoDocumento', 'documentoHabilitacao']);
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

