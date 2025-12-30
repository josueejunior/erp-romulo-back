<?php

namespace App\Http\Resources;

use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para transformar Domain Entity Fornecedor em JSON
 * Converte entidade de domínio para formato de resposta da API
 */
class FornecedorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Se for uma entidade de domínio, buscar modelo Eloquent para incluir relacionamentos
        if ($this->resource instanceof Fornecedor) {
            $fornecedorRepository = app(FornecedorRepositoryInterface::class);
            $fornecedorModel = $fornecedorRepository->buscarModeloPorId($this->resource->id);
            
            if (!$fornecedorModel) {
                // Fallback: retornar apenas dados da entidade
                return [
                    'id' => $this->resource->id,
                    'razao_social' => $this->resource->razaoSocial,
                    'cnpj' => $this->resource->cnpj,
                    'nome_fantasia' => $this->resource->nomeFantasia,
                    'email' => $this->resource->email,
                    'telefone' => $this->resource->telefone,
                    'cidade' => $this->resource->cidade,
                    'estado' => $this->resource->estado,
                    'cep' => $this->resource->cep,
                    'contato' => $this->resource->contato,
                    'observacoes' => $this->resource->observacoes,
                ];
            }
            
            // Usar modelo Eloquent para incluir relacionamentos se necessário
            return [
                'id' => $fornecedorModel->id,
                'razao_social' => $fornecedorModel->razao_social,
                'cnpj' => $fornecedorModel->cnpj,
                'nome_fantasia' => $fornecedorModel->nome_fantasia,
                'email' => $fornecedorModel->email,
                'telefone' => $fornecedorModel->telefone,
                'endereco' => $fornecedorModel->endereco,
                'cidade' => $fornecedorModel->cidade,
                'estado' => $fornecedorModel->estado,
                'cep' => $fornecedorModel->cep,
                'contato' => $fornecedorModel->contato,
                'observacoes' => $fornecedorModel->observacoes,
            ];
        }
        
        // Se já for um modelo Eloquent, usar diretamente
        return [
            'id' => $this->resource->id,
            'razao_social' => $this->resource->razao_social,
            'cnpj' => $this->resource->cnpj,
            'nome_fantasia' => $this->resource->nome_fantasia,
            'email' => $this->resource->email,
            'telefone' => $this->resource->telefone,
            'endereco' => $this->resource->endereco,
            'cidade' => $this->resource->cidade,
            'estado' => $this->resource->estado,
            'cep' => $this->resource->cep,
            'contato' => $this->resource->contato,
            'observacoes' => $this->resource->observacoes,
        ];
    }
}
