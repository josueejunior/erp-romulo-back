<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Application\Afiliado\DTOs\CriarAfiliadoDTO;
use App\Modules\Afiliado\Models\Afiliado;
use Illuminate\Support\Facades\Log;
use DomainException;

/**
 * Use Case para criar Afiliado
 */
final class CriarAfiliadoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(CriarAfiliadoDTO $dto): Afiliado
    {
        Log::debug('CriarAfiliadoUseCase::executar', [
            'nome' => $dto->nome,
            'email' => $dto->email,
            'codigo' => $dto->codigo,
        ]);

        // Validar documento único
        $existeDocumento = Afiliado::where('documento', $dto->documento)->exists();
        if ($existeDocumento) {
            throw new DomainException('Já existe um afiliado com este documento.');
        }

        // Validar email único
        $existeEmail = Afiliado::where('email', $dto->email)->exists();
        if ($existeEmail) {
            throw new DomainException('Já existe um afiliado com este e-mail.');
        }

        // Validar código único (se fornecido)
        if ($dto->codigo) {
            $existeCodigo = Afiliado::where('codigo', $dto->codigo)->exists();
            if ($existeCodigo) {
                throw new DomainException('Já existe um afiliado com este código.');
            }
        }

        // Validar percentuais
        if ($dto->percentualDesconto < 0 || $dto->percentualDesconto > 100) {
            throw new DomainException('O percentual de desconto deve estar entre 0 e 100.');
        }

        if ($dto->percentualComissao < 0 || $dto->percentualComissao > 100) {
            throw new DomainException('O percentual de comissão deve estar entre 0 e 100.');
        }

        // Preparar dados para criação
        $data = $dto->toArray();
        
        // Se não há contas_bancarias mas há dados bancários antigos, migrar
        if (empty($data['contas_bancarias']) && ($data['banco'] || $data['agencia'] || $data['conta'] || $data['pix'])) {
            $data['contas_bancarias'] = [[
                'banco' => $data['banco'] ?? '',
                'agencia' => $data['agencia'] ?? '',
                'conta' => $data['conta'] ?? '',
                'tipo_conta' => $data['tipo_conta'] ?? '',
                'pix' => $data['pix'] ?? '',
            ]];
        }

        // Criar afiliado
        $afiliado = Afiliado::create($data);

        Log::info('CriarAfiliadoUseCase - Afiliado criado', [
            'id' => $afiliado->id,
            'codigo' => $afiliado->codigo,
        ]);

        return $afiliado;
    }
}



