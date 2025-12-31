<?php

namespace App\Application\DocumentoHabilitacao\UseCases;

use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Documento de Habilitação
 */
class CriarDocumentoHabilitacaoUseCase
{
    public function __construct(
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
    ) {}

    public function executar(CriarDocumentoHabilitacaoDTO $dto): DocumentoHabilitacao
    {
        $documento = new DocumentoHabilitacao(
            id: null,
            empresaId: $dto->empresaId,
            tipo: $dto->tipo,
            numero: $dto->numero,
            identificacao: $dto->identificacao,
            dataEmissao: $dto->dataEmissao,
            dataValidade: $dto->dataValidade,
            arquivo: $dto->arquivo,
            ativo: $dto->ativo,
            observacoes: $dto->observacoes,
        );

        return $this->documentoRepository->criar($documento);
    }
}


