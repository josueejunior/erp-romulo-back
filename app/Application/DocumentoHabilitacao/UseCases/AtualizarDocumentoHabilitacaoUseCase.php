<?php

namespace App\Application\DocumentoHabilitacao\UseCases;

use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Atualizar Documento de Habilitação
 */
class AtualizarDocumentoHabilitacaoUseCase
{
    public function __construct(
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
    ) {}

    public function executar(int $id, CriarDocumentoHabilitacaoDTO $dto): DocumentoHabilitacao
    {
        $context = TenantContext::get();

        $documentoExistente = $this->documentoRepository->buscarPorId($id);
        if (!$documentoExistente) {
            throw new DomainException('Documento não encontrado.');
        }

        if ($documentoExistente->empresaId !== $context->empresaId) {
            throw new DomainException('Documento não pertence à empresa ativa.');
        }

        $documento = new DocumentoHabilitacao(
            id: $id,
            empresaId: $context->empresaId,
            tipo: $dto->tipo,
            numero: $dto->numero,
            identificacao: $dto->identificacao,
            dataEmissao: $dto->dataEmissao,
            dataValidade: $dto->dataValidade,
            arquivo: $dto->arquivo,
            ativo: $dto->ativo,
            observacoes: $dto->observacoes,
        );

        return $this->documentoRepository->atualizar($documento);
    }
}

