@extends('layouts.app')

@section('title', 'Novo Órgão')

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('orgaos.index') }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Novo Órgão</h1>
        <p class="text-gray-600 mt-1">Cadastre um novo órgão contratante</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('orgaos.store') }}">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">UASG</label>
                <input type="text" name="uasg" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('uasg') }}" placeholder="Código UASG">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Razão Social <span class="text-red-500">*</span>
                </label>
                <input type="text" name="razao_social" required 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('razao_social') }}" placeholder="Razão social do órgão">
                @error('razao_social')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">CNPJ</label>
                <input type="text" name="cnpj" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('cnpj') }}" placeholder="00.000.000/0000-00">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Endereço</label>
                <input type="text" name="endereco" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('endereco') }}" placeholder="Rua, número, complemento">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Cidade</label>
                <input type="text" name="cidade" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('cidade') }}" placeholder="Nome da cidade">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Estado</label>
                <input type="text" name="estado" maxlength="2"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors uppercase" 
                       value="{{ old('estado') }}" placeholder="SP">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">CEP</label>
                <input type="text" name="cep" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('cep') }}" placeholder="00000-000">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                <input type="email" name="email" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('email') }}" placeholder="email@exemplo.com">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone</label>
                <input type="text" name="telefone" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('telefone') }}" placeholder="(00) 00000-0000">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="4" 
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                          placeholder="Observações adicionais...">{{ old('observacoes') }}</textarea>
            </div>
        </div>

        <div class="mt-8 flex gap-4 pt-6 border-t border-gray-200">
            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                <x-heroicon-o-check class="w-5 h-5" />
                Salvar Órgão
            </button>
            <a href="{{ route('orgaos.index') }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
