<?php

declare(strict_types=1);

namespace App\Application\Empresa\UseCases;

use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Application\Afiliado\UseCases\ValidarSelfReferralUseCase;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para registrar afiliado na empresa
 * 
 * Segue padrão DDD - Use Case na camada de Application
 * 
 * 🔥 Validação de Self-Referral: Bloqueia afiliado usar próprio link
 */
final class RegistrarAfiliadoNaEmpresaUseCase
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ValidarSelfReferralUseCase $validarSelfReferralUseCase,
    ) {}

    /**
     * Executa o use case
     * 
     * @param int $empresaId ID da empresa
     * @param int $afiliadoId ID do afiliado
     * @param string $codigo Código do afiliado usado
     * @param float $descontoAplicado Percentual de desconto aplicado
     * @param string $cnpjEmpresa CNPJ da empresa (para validação de self-referral)
     * @param string|null $cpfRepresentante CPF do representante legal (opcional)
     * @throws DomainException Se a empresa não for encontrada ou for self-referral
     */
    public function executar(
        int $empresaId,
        int $afiliadoId,
        string $codigo,
        float $descontoAplicado,
        string $cnpjEmpresa,
        ?string $cpfRepresentante = null
    ): void {
        Log::debug('RegistrarAfiliadoNaEmpresaUseCase::executar', [
            'empresa_id' => $empresaId,
            'afiliado_id' => $afiliadoId,
            'codigo' => $codigo,
            'desconto' => $descontoAplicado,
            'cnpj_empresa' => $cnpjEmpresa,
        ]);

        // 🔥 VALIDAÇÃO DE SELF-REFERRAL: Bloquear afiliado usar próprio link
        $this->validarSelfReferralUseCase->executar(
            afiliadoId: $afiliadoId,
            cnpjCliente: $cnpjEmpresa,
            cpfCliente: $cpfRepresentante
        );

        // Validar que a empresa existe
        $empresa = $this->empresaRepository->buscarPorId($empresaId);
        if (!$empresa) {
            throw new DomainException('Empresa não encontrada.');
        }

        // Validar desconto
        if ($descontoAplicado < 0 || $descontoAplicado > 100) {
            throw new DomainException('O percentual de desconto deve estar entre 0 e 100.');
        }

        // Atualizar empresa com dados do afiliado
        $this->empresaRepository->atualizarAfiliado(
            empresaId: $empresaId,
            afiliadoId: $afiliadoId,
            codigo: $codigo,
            descontoAplicado: $descontoAplicado
        );

        Log::info('RegistrarAfiliadoNaEmpresaUseCase - Afiliado registrado na empresa', [
            'empresa_id' => $empresaId,
            'afiliado_id' => $afiliadoId,
            'codigo' => $codigo,
        ]);
    }
}

