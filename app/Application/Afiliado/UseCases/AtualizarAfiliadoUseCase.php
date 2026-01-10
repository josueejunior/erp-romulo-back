<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Application\Afiliado\DTOs\AtualizarAfiliadoDTO;
use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Use Case para atualizar Afiliado
 */
final class AtualizarAfiliadoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(AtualizarAfiliadoDTO $dto): Afiliado
    {
        Log::debug('AtualizarAfiliadoUseCase::executar', [
            'id' => $dto->id,
        ]);

        // Buscar afiliado
        $afiliado = Afiliado::find($dto->id);
        if (!$afiliado) {
            throw new DomainException('Afiliado não encontrado.');
        }

        // Validar documento único (se alterado)
        if ($dto->documento !== null && $dto->documento !== $afiliado->documento) {
            $existeDocumento = Afiliado::where('documento', $dto->documento)
                ->where('id', '!=', $dto->id)
                ->exists();
            if ($existeDocumento) {
                throw new DomainException('Já existe um afiliado com este documento.');
            }
        }

        // Validar email único (se alterado)
        if ($dto->email !== null && $dto->email !== $afiliado->email) {
            $existeEmail = Afiliado::where('email', $dto->email)
                ->where('id', '!=', $dto->id)
                ->exists();
            if ($existeEmail) {
                throw new DomainException('Já existe um afiliado com este e-mail.');
            }
        }

        // Validar código único (se alterado)
        if ($dto->codigo !== null && $dto->codigo !== $afiliado->codigo) {
            $existeCodigo = Afiliado::where('codigo', $dto->codigo)
                ->where('id', '!=', $dto->id)
                ->exists();
            if ($existeCodigo) {
                throw new DomainException('Já existe um afiliado com este código.');
            }
        }

        // Validar percentuais
        if ($dto->percentualDesconto !== null && ($dto->percentualDesconto < 0 || $dto->percentualDesconto > 100)) {
            throw new DomainException('O percentual de desconto deve estar entre 0 e 100.');
        }

        if ($dto->percentualComissao !== null && ($dto->percentualComissao < 0 || $dto->percentualComissao > 100)) {
            throw new DomainException('O percentual de comissão deve estar entre 0 e 100.');
        }

        // Preparar dados para atualização
        $data = $dto->toArray();
        
        // Se não há contas_bancarias mas há dados bancários antigos, migrar
        // 🔥 CORREÇÃO: Usar ?? para evitar "Undefined array key" quando campos não existem
        $banco = $data['banco'] ?? null;
        $agencia = $data['agencia'] ?? null;
        $conta = $data['conta'] ?? null;
        $pix = $data['pix'] ?? null;
        
        if (empty($data['contas_bancarias']) && ($banco || $agencia || $conta || $pix)) {
            $data['contas_bancarias'] = [[
                'banco' => $banco ?? '',
                'agencia' => $agencia ?? '',
                'conta' => $conta ?? '',
                'tipo_conta' => $data['tipo_conta'] ?? '',
                'pix' => $pix ?? '',
            ]];
        }

        // Atualizar afiliado
        $afiliado->update($data);

        Log::info('AtualizarAfiliadoUseCase - Afiliado atualizado', [
            'id' => $afiliado->id,
        ]);

        return $afiliado->fresh();
    }
}



