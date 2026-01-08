<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Use Case para excluir Afiliado
 */
final class ExcluirAfiliadoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(int $id): void
    {
        Log::debug('ExcluirAfiliadoUseCase::executar', ['id' => $id]);

        $afiliado = Afiliado::find($id);
        if (!$afiliado) {
            throw new DomainException('Afiliado não encontrado.');
        }

        // Verificar se tem indicações ativas
        $indicacoesAtivas = $afiliado->indicacoesAtivas()->count();
        if ($indicacoesAtivas > 0) {
            throw new DomainException(
                "Não é possível excluir este afiliado pois ele possui {$indicacoesAtivas} indicação(ões) ativa(s)."
            );
        }

        // Soft delete
        $afiliado->delete();

        Log::info('ExcluirAfiliadoUseCase - Afiliado excluído', [
            'id' => $id,
            'codigo' => $afiliado->codigo,
        ]);
    }
}

