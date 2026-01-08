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
 * Use Case: Criar Documento de Habilitação
 * 
 * Responsabilidades:
 * - Validar contexto de empresa
 * - Verificar duplicidade
 * - Fazer upload do arquivo se necessário
 * - Criar documento no repositório
 * - Criar versão inicial do arquivo
 */
class CriarDocumentoHabilitacaoUseCase
{
    public function __construct(
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
    ) {}

    public function executar(CriarDocumentoHabilitacaoDTO $dto): DocumentoHabilitacao
    {
        $context = TenantContext::get();

        if (!$context->empresaId) {
            throw new DomainException('Empresa não identificada no contexto. Verifique se o middleware está configurado corretamente.');
        }

        Log::debug('CriarDocumentoHabilitacaoUseCase::executar - Iniciando', [
            'empresa_id' => $context->empresaId,
            'tipo' => $dto->tipo,
            'numero' => $dto->numero,
            'tem_arquivo' => $dto->temArquivoParaUpload(),
        ]);

        // Verificar duplicidade
        $this->verificarDuplicidade($dto->tipo, $dto->numero, $context->empresaId);

        // Processar upload do arquivo se houver
        $arquivoPath = $dto->arquivoPath;
        $arquivoMeta = null;
        
        if ($dto->temArquivoParaUpload()) {
            $uploadResult = $this->processarUploadArquivo($dto->arquivoUpload);
            $arquivoPath = $uploadResult['nome'];
            $arquivoMeta = $uploadResult;
            
            Log::debug('CriarDocumentoHabilitacaoUseCase::executar - Arquivo uploaded', [
                'nome' => $arquivoPath,
                'caminho' => $uploadResult['caminho'],
            ]);
        }

        $documento = new DocumentoHabilitacao(
            id: null,
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

        $documentoCriado = $this->documentoRepository->criar($documento);

        // Criar versão inicial se tiver arquivo
        if ($arquivoMeta) {
            $this->criarVersao($documentoCriado, 1, $arquivoMeta);
        }

        Log::info('CriarDocumentoHabilitacaoUseCase::executar - Documento criado', [
            'documento_id' => $documentoCriado->id,
            'empresa_id' => $documentoCriado->empresaId,
        ]);

        return $documentoCriado;
    }

    /**
     * Verifica se já existe documento com mesmo tipo e número
     */
    private function verificarDuplicidade(?string $tipo, ?string $numero, int $empresaId): void
    {
        if (!$tipo || !$numero) {
            return;
        }

        $model = \App\Modules\Documento\Models\DocumentoHabilitacao::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('numero', $numero)
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
