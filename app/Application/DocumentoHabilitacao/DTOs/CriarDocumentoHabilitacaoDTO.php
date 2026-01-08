<?php

namespace App\Application\DocumentoHabilitacao\DTOs;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * DTO para criação de documento de habilitação
 * O empresaId é obtido do TenantContext pelo Use Case, não vem do controller
 */
class CriarDocumentoHabilitacaoDTO
{
    public function __construct(
        public readonly ?string $tipo = null,
        public readonly ?string $numero = null,
        public readonly ?string $identificacao = null,
        public readonly ?Carbon $dataEmissao = null,
        public readonly ?Carbon $dataValidade = null,
        public readonly ?string $arquivoPath = null, // Path do arquivo já salvo
        public readonly ?UploadedFile $arquivoUpload = null, // Arquivo para upload
        public readonly bool $ativo = true,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Verificar se arquivo é UploadedFile ou string
        $arquivoUpload = null;
        $arquivoPath = null;
        
        if (isset($data['arquivo'])) {
            if ($data['arquivo'] instanceof UploadedFile) {
                $arquivoUpload = $data['arquivo'];
            } elseif (is_string($data['arquivo'])) {
                $arquivoPath = $data['arquivo'];
            }
        }

        return new self(
            tipo: $data['tipo'] ?? null,
            numero: $data['numero'] ?? null,
            identificacao: $data['identificacao'] ?? null,
            dataEmissao: isset($data['data_emissao']) && $data['data_emissao'] ? Carbon::parse($data['data_emissao']) : null,
            dataValidade: isset($data['data_validade']) && $data['data_validade'] ? Carbon::parse($data['data_validade']) : null,
            arquivoPath: $arquivoPath,
            arquivoUpload: $arquivoUpload,
            ativo: isset($data['ativo']) ? (bool) $data['ativo'] : true,
            observacoes: $data['observacoes'] ?? null,
        );
    }

    /**
     * Verifica se tem arquivo para upload
     */
    public function temArquivoParaUpload(): bool
    {
        return $this->arquivoUpload !== null;
    }

    /**
     * Retorna o path do arquivo (já salvo ou null)
     */
    public function getArquivoPath(): ?string
    {
        return $this->arquivoPath;
    }
}
