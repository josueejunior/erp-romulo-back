@extends('layouts.app')

@section('title', 'Novo Setor')

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('orgaos.show', $orgao) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Novo Setor</h1>
        <p class="text-gray-600 mt-1">Cadastre um novo setor para {{ $orgao->razao_social }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('setors.store') }}">
        @csrf
        <input type="hidden" name="orgao_id" value="{{ $orgao->id }}">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Órgão
                </label>
                <input type="text" value="{{ $orgao->razao_social }}" disabled
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-600">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Nome do Setor <span class="text-red-500">*</span>
                </label>
                <input type="text" name="nome" required 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('nome') }}" placeholder="Nome do setor">
                @error('nome')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                <input type="email" name="email" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('email') }}" placeholder="email@exemplo.com">
                @error('email')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
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
                Salvar Setor
            </button>
            <a href="{{ route('orgaos.show', $orgao) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
