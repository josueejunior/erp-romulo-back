<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Validar Self-Referral
 * 
 * Bloqueia que o afiliado use o próprio link para comprar o sistema para si mesmo
 * 
 * Regra: O CPF/CNPJ do afiliado não pode ser o mesmo do cliente final
 */
final class ValidarSelfReferralUseCase
{
    /**
     * Valida se não é self-referral
     * 
     * @param int $afiliadoId ID do afiliado
     * @param string $cnpjCliente CNPJ do cliente que está se cadastrando
     * @param string|null $cpfCliente CPF do cliente (opcional, para validação adicional)
     * @throws DomainException Se for self-referral
     */
    public function executar(
        int $afiliadoId,
        string $cnpjCliente,
        ?string $cpfCliente = null
    ): void {
        Log::debug('ValidarSelfReferralUseCase::executar', [
            'afiliado_id' => $afiliadoId,
            'cnpj_cliente' => $cnpjCliente,
            'cpf_cliente' => $cpfCliente,
        ]);

        // Buscar afiliado
        $afiliado = Afiliado::find($afiliadoId);
        if (!$afiliado) {
            throw new DomainException('Afiliado não encontrado.');
        }

        // Normalizar documentos (remover formatação)
        $cnpjClienteLimpo = preg_replace('/\D/', '', $cnpjCliente);
        $documentoAfiliadoLimpo = preg_replace('/\D/', '', $afiliado->documento);

        // Validar se CNPJ do cliente é igual ao documento do afiliado
        if ($cnpjClienteLimpo === $documentoAfiliadoLimpo) {
            Log::warning('ValidarSelfReferralUseCase - Self-referral detectado (CNPJ)', [
                'afiliado_id' => $afiliadoId,
                'documento_afiliado' => $afiliado->documento,
                'cnpj_cliente' => $cnpjCliente,
            ]);

            throw new DomainException(
                'Você não pode usar seu próprio link de afiliado para se cadastrar. ' .
                'O programa de afiliados é destinado apenas para indicações de terceiros.'
            );
        }

        // Validação adicional: Se o afiliado for CPF e o cliente também forneceu CPF
        if ($afiliado->tipo_documento === 'cpf' && $cpfCliente) {
            $cpfClienteLimpo = preg_replace('/\D/', '', $cpfCliente);
            
            if ($cpfClienteLimpo === $documentoAfiliadoLimpo) {
                Log::warning('ValidarSelfReferralUseCase - Self-referral detectado (CPF)', [
                    'afiliado_id' => $afiliadoId,
                    'documento_afiliado' => $afiliado->documento,
                    'cpf_cliente' => $cpfCliente,
                ]);

                throw new DomainException(
                    'Você não pode usar seu próprio link de afiliado para se cadastrar. ' .
                    'O programa de afiliados é destinado apenas para indicações de terceiros.'
                );
            }
        }

        Log::debug('ValidarSelfReferralUseCase - Validação passou (não é self-referral)', [
            'afiliado_id' => $afiliadoId,
            'cnpj_cliente' => $cnpjCliente,
        ]);
    }
}



