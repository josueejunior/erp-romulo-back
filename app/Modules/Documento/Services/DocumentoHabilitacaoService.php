<?php

namespace App\Modules\Documento\Services;

use App\Services\BaseService;
use App\Models\DocumentoHabilitacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DocumentoHabilitacaoService extends BaseService
{
    protected static string $model = DocumentoHabilitacao::class;

    public function createListParamBag(array $values): array
    {
        return [
            'search' => $values['search'] ?? null,
            'vencendo' => $values['vencendo'] ?? false,
            'todos' => $values['todos'] ?? false,
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
        ];
    }

    public function list(array $params = []): LengthAwarePaginator
    {
        $builder = $this->createQueryBuilder();

        // Busca livre
        if (isset($params['search']) && $params['search']) {
            $search = $params['search'];
            $builder->where(function($q) use ($search) {
                $q->where('tipo', 'like', "%{$search}%")
                  ->orWhere('numero', 'like', "%{$search}%");
            });
        }

        // Filtro por documentos vencendo
        if (isset($params['vencendo']) && $params['vencendo']) {
            $builder->whereNotNull('data_validade')
                  ->where('data_validade', '>=', now())
                  ->where('data_validade', '<=', now()->addDays(30));
        }

        // Ordenação
        $builder->orderBy('data_validade', 'asc');

        // Se não for paginação, retornar todos em um paginator
        if (isset($params['todos']) && $params['todos']) {
            $all = $builder->orderBy('tipo', 'asc')->get();
            $perPage = $all->count() > 0 ? $all->count() : 1;
            $page = 1;
            
            // Criar um LengthAwarePaginator manualmente com todos os itens
            return new \Illuminate\Pagination\LengthAwarePaginator(
                $all,
                $all->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        // Paginação normal
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'tipo' => 'required|string|max:255',
            'numero' => 'nullable|string|max:255',
            'identificacao' => 'nullable|string|max:255',
            'data_emissao' => 'nullable|date',
            'data_validade' => 'nullable|date',
            'arquivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'observacoes' => 'nullable|string',
        ]);
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return $this->validateStoreData($data);
    }

    public function store(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Processar arquivo se presente
        if (isset($data['arquivo']) && $data['arquivo'] instanceof \Illuminate\Http\UploadedFile) {
            $arquivo = $data['arquivo'];
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
            $data['arquivo'] = $nomeArquivo;
        }

        return parent::store($data);
    }

    public function update(int|string $id, array $data): \Illuminate\Database\Eloquent\Model
    {
        $documento = $this->findById($id);
        
        if (!$documento) {
            throw new \Exception('Documento não encontrado');
        }

        // Processar arquivo se presente
        if (isset($data['arquivo']) && $data['arquivo'] instanceof \Illuminate\Http\UploadedFile) {
            // Deletar arquivo antigo se existir
            if ($documento->arquivo) {
                Storage::disk('public')->delete('documentos-habilitacao/' . $documento->arquivo);
            }
            
            $arquivo = $data['arquivo'];
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
            $data['arquivo'] = $nomeArquivo;
        }

        return parent::update($id, $data);
    }

    public function deleteById(int|string $id): bool
    {
        $documento = $this->findById($id);
        
        if (!$documento) {
            return false;
        }

        if ($documento->processoDocumentos()->count() > 0) {
            throw new \Exception('Não é possível excluir um documento que está vinculado a processos.');
        }

        // Deletar arquivo se existir
        if ($documento->arquivo) {
            Storage::disk('public')->delete('documentos-habilitacao/' . $documento->arquivo);
        }

        return $documento->forceDelete();
    }
}


