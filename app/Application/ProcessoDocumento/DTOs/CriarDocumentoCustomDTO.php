<?php

namespace App\Application\ProcessoDocumento\DTOs;

/**
 * DTO para criação de documento customizado
 */
class CriarDocumentoCustomDTO
{
    /** Valores: edital, termo_referencia, outro. Null = documento do checklist (não inicial). */
    public function __construct(
        public readonly string $tituloCustom,
        public readonly bool $exigido,
        public readonly bool $disponivelEnvio,
        public readonly string $status, // pendente, possui, anexado
        public readonly ?string $observacoes = null,
        public readonly ?string $tipoDocumentoInicial = null, // edital, termo_referencia, outro
    ) {}

    /**
     * Criar DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        $tipo = $data['tipo_documento_inicial'] ?? null;
        if ($tipo !== null && !in_array($tipo, ['edital', 'termo_referencia', 'outro'], true)) {
            $tipo = null;
        }
        $titulo = $data['titulo_custom'] ?? 'Documento';
        if ($tipo === 'edital' && ($data['titulo_custom'] ?? '') === '') {
            $titulo = 'Edital';
        }
        if ($tipo === 'termo_referencia' && ($data['titulo_custom'] ?? '') === '') {
            $titulo = 'Termo de Referência';
        }
        return new self(
            tituloCustom: $titulo,
            exigido: (bool) ($data['exigido'] ?? ($tipo !== null)),
            disponivelEnvio: (bool) ($data['disponivel_envio'] ?? false),
            status: $data['status'] ?? 'pendente',
            observacoes: $data['observacoes'] ?? null,
            tipoDocumentoInicial: $tipo,
        );
    }
}

