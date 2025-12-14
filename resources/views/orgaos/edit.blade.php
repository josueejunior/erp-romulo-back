@extends('layouts.app')

@section('title', 'Editar Órgão')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Editar Órgão</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('orgaos.update', $orgao) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">UASG</label>
                <input type="text" name="uasg" 
                       class="w-full border rounded px-3 py-2" value="{{ old('uasg', $orgao->uasg) }}">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Razão Social *</label>
                <input type="text" name="razao_social" required 
                       class="w-full border rounded px-3 py-2" value="{{ old('razao_social', $orgao->razao_social) }}">
                @error('razao_social')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">CNPJ</label>
                <input type="text" name="cnpj" 
                       class="w-full border rounded px-3 py-2" value="{{ old('cnpj', $orgao->cnpj) }}" 
                       placeholder="00.000.000/0000-00">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Endereço</label>
                <input type="text" name="endereco" 
                       class="w-full border rounded px-3 py-2" value="{{ old('endereco', $orgao->endereco) }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cidade</label>
                <input type="text" name="cidade" 
                       class="w-full border rounded px-3 py-2" value="{{ old('cidade', $orgao->cidade) }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <input type="text" name="estado" maxlength="2"
                       class="w-full border rounded px-3 py-2" value="{{ old('estado', $orgao->estado) }}" 
                       placeholder="SP">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">CEP</label>
                <input type="text" name="cep" 
                       class="w-full border rounded px-3 py-2" value="{{ old('cep', $orgao->cep) }}" 
                       placeholder="00000-000">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email" 
                       class="w-full border rounded px-3 py-2" value="{{ old('email', $orgao->email) }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
                <input type="text" name="telefone" 
                       class="w-full border rounded px-3 py-2" value="{{ old('telefone', $orgao->telefone) }}" 
                       placeholder="(00) 00000-0000">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="3" 
                          class="w-full border rounded px-3 py-2">{{ old('observacoes', $orgao->observacoes) }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Atualizar Órgão
            </button>
            <a href="{{ route('orgaos.show', $orgao) }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
