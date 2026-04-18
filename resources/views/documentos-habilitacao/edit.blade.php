@extends('layouts.app')

@section('title', 'Editar Documento de Habilitação')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Editar Documento de Habilitação</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('documentos-habilitacao.update', $documentoHabilitacao) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo do Documento *</label>
                <input type="text" name="tipo" required 
                       class="w-full border rounded px-3 py-2" value="{{ old('tipo', $documentoHabilitacao->tipo) }}"
                       placeholder="Ex: CND Federal, FGTS, Balanço, Contrato Social...">
                @error('tipo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Número</label>
                <input type="text" name="numero" 
                       class="w-full border rounded px-3 py-2" value="{{ old('numero', $documentoHabilitacao->numero) }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Identificação</label>
                <input type="text" name="identificacao" 
                       class="w-full border rounded px-3 py-2" value="{{ old('identificacao', $documentoHabilitacao->identificacao) }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data de Emissão</label>
                <input type="date" name="data_emissao" 
                       class="w-full border rounded px-3 py-2" 
                       value="{{ old('data_emissao', $documentoHabilitacao->data_emissao ? $documentoHabilitacao->data_emissao->format('Y-m-d') : '') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data de Validade</label>
                <input type="date" name="data_validade" 
                       class="w-full border rounded px-3 py-2" 
                       value="{{ old('data_validade', $documentoHabilitacao->data_validade ? $documentoHabilitacao->data_validade->format('Y-m-d') : '') }}">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo (PDF, JPG, PNG - Máx. 10MB)</label>
                @if($documentoHabilitacao->arquivo)
                <div class="mb-2">
                    <p class="text-sm text-gray-600">Arquivo atual:</p>
                    <a href="{{ asset('storage/documentos/' . $documentoHabilitacao->arquivo) }}" target="_blank" 
                       class="text-blue-600 hover:text-blue-900 text-sm">
                        {{ $documentoHabilitacao->arquivo }}
                    </a>
                </div>
                @endif
                <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full border rounded px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Deixe em branco para manter o arquivo atual</p>
                @error('arquivo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="3" 
                          class="w-full border rounded px-3 py-2">{{ old('observacoes', $documentoHabilitacao->observacoes) }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Atualizar Documento
            </button>
            <a href="{{ route('documentos-habilitacao.show', $documentoHabilitacao) }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
