<?php

namespace App\Modules\Documento\Services;

use App\Services\BaseService;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use App\Modules\Documento\Models\DocumentoHabilitacaoLog;
use App\Modules\Documento\Models\DocumentoHabilitacaoVersao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DocumentoHabilitacaoService extends BaseService
{
    protected static string $model = DocumentoHabilitacao::class;

    public function createListParamBag(array $values): array
    {
        return [
            'search' => $values['search'] ?? null,
            'vencendo' => isset($values['vencendo']) ? (bool) $values['vencendo'] : false,
            'vencidos' => isset($values['vencidos']) ? (bool) $values['vencidos'] : false,
            'ativo' => isset($values['ativo']) ? (bool) $values['ativo'] : null,
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
            $hoje = \Carbon\Carbon::now()->startOfDay();
            $em30Dias = \Carbon\Carbon::now()->addDays(30)->endOfDay();
            $builder->whereNotNull('data_validade')
                  ->where('data_validade', '>=', $hoje)
                  ->where('data_validade', '<=', $em30Dias)
                  ->where('ativo', true);
        }

        // Filtro por documentos vencidos
        if (isset($params['vencidos']) && $params['vencidos']) {
            $hoje = \Carbon\Carbon::now()->startOfDay();
            $builder->whereNotNull('data_validade')
                  ->where('data_validade', '<', $hoje)
                  ->where('ativo', true);
        }

        // Filtro por ativo (só aplicar se não estiver usando filtros de vencimento, que já forçam ativo=true)
        if (isset($params['ativo']) && $params['ativo'] !== null && !isset($params['vencidos']) && !isset($params['vencendo'])) {
            $builder->where('ativo', $params['ativo']);
        }

        // Ordenação
        if (isset($params['vencidos']) && $params['vencidos']) {
            // Documentos vencidos: ordenar por data de validade (mais recente primeiro)
            $builder->orderBy('data_validade', 'desc');
        } elseif (isset($params['vencendo']) && $params['vencendo']) {
            // Documentos vencendo: ordenar por data de validade (mais próximo primeiro)
            $builder->orderBy('data_validade', 'asc');
        } else {
            // Ordenação padrão por data de validade
            $builder->orderBy('data_validade', 'asc');
        }

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
        $empresaId = app('current_empresa_id');
        $userId = Auth::id();

        $this->assertNoDuplicate($data, null, $empresaId);

        if (isset($data['arquivo']) && $data['arquivo'] instanceof \Illuminate\Http\UploadedFile) {
            $arquivo = $data['arquivo'];
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $caminho = $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
            $data['arquivo'] = $nomeArquivo;
            $data['versao_meta'] = [
                'caminho' => $caminho,
                'mime' => $arquivo->getMimeType(),
                'tamanho' => $arquivo->getSize(),
                'user_id' => $userId,
            ];
        }

        $data['empresa_id'] = $data['empresa_id'] ?? $empresaId;

        $model = parent::store($data);

        if (!empty($data['versao_meta'])) {
            $this->criarVersao($model, 1, $data['versao_meta']);
            $this->logAction($model, 'create', ['versao' => 1]);
        }

        return $model;
    }

    public function update(int|string $id, array $data): \Illuminate\Database\Eloquent\Model
    {
        $documento = $this->findById($id);
        
        if (!$documento) {
            throw new \Exception('Documento não encontrado');
        }

        $empresaId = app('current_empresa_id');
        $userId = Auth::id();

        $this->assertNoDuplicate($data, $documento->id, $empresaId);

        // Processar arquivo se presente
        if (isset($data['arquivo']) && $data['arquivo'] instanceof \Illuminate\Http\UploadedFile) {
            $arquivo = $data['arquivo'];
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $caminho = $arquivo->storeAs('documentos-habilitacao', $nomeArquivo, 'public');
            $data['arquivo'] = $nomeArquivo;
            $data['versao_meta'] = [
                'caminho' => $caminho,
                'mime' => $arquivo->getMimeType(),
                'tamanho' => $arquivo->getSize(),
                'user_id' => $userId,
            ];
        }

        $data['empresa_id'] = $data['empresa_id'] ?? $empresaId;

        $updated = parent::update($id, $data);

        if (!empty($data['versao_meta'])) {
            $nextVersion = ($documento->versoes()->max('versao') ?? 0) + 1;
            $this->criarVersao($updated, $nextVersion, $data['versao_meta']);
            $this->logAction($updated, 'update', ['versao' => $nextVersion]);
        }

        return $updated;
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

    public function logAction(DocumentoHabilitacao $documento, string $acao, array $meta = []): void
    {
        DocumentoHabilitacaoLog::create([
            'empresa_id' => $documento->empresa_id,
            'documento_habilitacao_id' => $documento->id,
            'user_id' => Auth::id(),
            'acao' => $acao,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => $meta,
        ]);
    }

    protected function criarVersao($documento, int $versao, array $meta): void
    {
        DocumentoHabilitacaoVersao::create([
            'empresa_id' => $documento->empresa_id,
            'documento_habilitacao_id' => $documento->id,
            'user_id' => $meta['user_id'] ?? null,
            'versao' => $versao,
            'nome_arquivo' => $documento->arquivo,
            'caminho' => $meta['caminho'] ?? $documento->arquivo,
            'mime' => $meta['mime'] ?? null,
            'tamanho_bytes' => $meta['tamanho'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function assertNoDuplicate(array $data, ?int $ignoreId, ?int $empresaId): void
    {
        $tipo = $data['tipo'] ?? null;
        $numero = $data['numero'] ?? null;
        if (!$tipo || !$numero || !$empresaId) {
            return;
        }

        $query = DocumentoHabilitacao::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('numero', $numero);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new \DomainException('Já existe um documento com este tipo e número para esta empresa.');
        }
    }
}


