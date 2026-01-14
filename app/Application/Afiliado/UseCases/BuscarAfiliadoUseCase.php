<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Use Case para buscar Afiliado por ID
 */
final class BuscarAfiliadoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(int $id): Afiliado
    {
        Log::debug('BuscarAfiliadoUseCase::executar', ['id' => $id]);

        $afiliado = Afiliado::with([
            'indicacoes' => function ($query) {
                $query->orderBy('indicado_em', 'desc');
            }
        ])
        ->withCount([
            'indicacoes',
            'indicacoesAtivas',
            'indicacoesInadimplentes',
            'indicacoesCanceladas',
        ])
        ->find($id);

        if (!$afiliado) {
            throw new DomainException('Afiliado não encontrado.');
        }

        return $afiliado;
    }

    /**
     * Busca afiliado por código (cupom)
     */
    public function buscarPorCodigo(string $codigo): ?Afiliado
    {
        Log::debug('BuscarAfiliadoUseCase::buscarPorCodigo', ['codigo' => $codigo]);

        return Afiliado::where('codigo', $codigo)
            ->where('ativo', true)
            ->first();
    }
}









