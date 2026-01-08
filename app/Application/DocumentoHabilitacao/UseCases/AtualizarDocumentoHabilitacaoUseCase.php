<?php

namespace App\Application\DocumentoHabilitacao\UseCases;

use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Modules\Documento\Models\DocumentoHabilitacaoVersao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use DomainException;

/**
 * Use Case: Atualizar Documento de Habilitação
 * 
 * Responsabilidades:
 * - Validar contexto de empresa
 * - Verificar se documento existe e pertence à empresa
 * - Verificar duplicidade (se tipo/número mudaram)
 * - Fazer upload do novo arquivo se necessário
 * - Atualizar documento no repositório
 * - Criar nova versão do arquivo
 */
class AtualizarDocumentoHabilitacaoUseCase
{
    public function __construct(
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
    ) {}

    public function executar(int $id, CriarDocumentoHabilitacaoDTO $dto): DocumentoHabilitacao
    {
        $context = TenantContext::get();

        if (!$context->empresaId) {
            throw new DomainException('Empresa não identificada no contexto. Verifique se o middleware está configurado corretamente.');
        }

        Log::debug('AtualizarDocumentoHabilitacaoUseCase::executar - Iniciando', [
            'documento_id' => $id,
            'empresa_id' => $context->empresaId,
            'tipo' => $dto->tipo,
            'numero' => $dto->numero,
            'tem_arquivo' => $dto->temArquivoParaUpload(),
        ]);

        $documentoExistente = $this->documentoRepository->buscarPorId($id);
        if (!$documentoExistente) {
            throw new DomainException('Documento não encontrado.');
        }

        if ($documentoExistente->empresaId !== $context->empresaId) {
            throw new DomainException('Documento não pertence à empresa ativa.');
        }

        // Verificar duplicidade (excluindo o documento atual)
        $this->verificarDuplicidade($dto->tipo, $dto->numero, $context->empresaId, $id);

        // Processar upload do arquivo se houver
        $arquivoPath = $dto->arquivoPath ?? $documentoExistente->arquivo;
        $arquivoMeta = null;
        
        if ($dto->temArquivoParaUpload()) {
            $uploadResult = $this->processarUploadArquivo($dto->arquivoUpload);
            $arquivoPath = $uploadResult['nome'];
            $arquivoMeta = $uploadResult;
            
            Log::debug('AtualizarDocumentoHabilitacaoUseCase::executar - Arquivo uploaded', [
                'nome' => $arquivoPath,
                'caminho' => $uploadResult['caminho'],
            ]);
        }

        $documento = new DocumentoHabilitacao(
            id: $id,
            empresaId: $context->empresaId,
            tipo: $dto->tipo,
            numero: $dto->numero,
            identificacao: $dto->identificacao,
            dataEmissao: $dto->dataEmissao,
            dataValidade: $dto->dataValidade,
            arquivo: $arquivoPath,
            ativo: $dto->ativo,
            observacoes: $dto->observacoes,
        );

        $documentoAtualizado = $this->documentoRepository->atualizar($documento);

        // Criar nova versão se tiver novo arquivo
        if ($arquivoMeta) {
            $nextVersion = $this->getProximaVersao($id);
            $this->criarVersao($documentoAtualizado, $nextVersion, $arquivoMeta);
        }

        Log::info('AtualizarDocumentoHabilitacaoUseCase::executar - Documento atualizado', [
            'documento_id' => $documentoAtualizado->id,
            'empresa_id' => $documentoAtualizado->empresaId,
        ]);

        return $documentoAtualizado;
    }

    /**
     * Verifica se já existe documento com mesmo tipo e número
     */
    private function verificarDuplicidade(?string $tipo, ?string $numero, int $empresaId, int $ignoreId): void
    {
        if (!$tipo || !$numero) {
            return;
        }

        $model = \App\Modules\Documento\Models\DocumentoHabilitacao::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('numero', $numero)
            ->where('id', '!=', $ignoreId)
            ->first();

        if ($model) {
            throw new DomainException('Já existe um documento com este tipo e número para esta empresa.');
        }
    }

    /**
     * Processa o upload do arquivo
     */
    private function processarUploadArquivo($arquivo): array
    {
        $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
        $caminho = $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');

        return [
            'nome' => $nomeArquivo,
            'caminho' => $caminho,
            'mime' => $arquivo->getMimeType(),
            'tamanho' => $arquivo->getSize(),
            'user_id' => Auth::id(),
        ];
    }

    /**
     * Obtém próxima versão do documento
     */
    private function getProximaVersao(int $documentoId): int
    {
        $maxVersao = DocumentoHabilitacaoVersao::where('documento_habilitacao_id', $documentoId)
            ->max('versao');
        
        return ($maxVersao ?? 0) + 1;
    }

    /**
     * Cria versão do arquivo
     */
    private function criarVersao(DocumentoHabilitacao $documento, int $versao, array $meta): void
    {
        DocumentoHabilitacaoVersao::create([
            'empresa_id' => $documento->empresaId,
            'documento_habilitacao_id' => $documento->id,
            'user_id' => $meta['user_id'] ?? null,
            'versao' => $versao,
            'nome_arquivo' => $meta['nome'],
            'caminho' => $meta['caminho'],
            'mime' => $meta['mime'] ?? null,
            'tamanho_bytes' => $meta['tamanho'] ?? null,
        ]);
    }
}
