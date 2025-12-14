@extends('layouts.app')

@section('title', 'Novo Contrato - ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Novo Contrato</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('contratos.store', $processo) }}">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Número do Contrato <span class="text-red-500">*</span>
                </label>
                <input type="text" name="numero" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('numero') }}" placeholder="Número do contrato">
                @error('numero')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Situação <span class="text-red-500">*</span>
                </label>
                <select name="situacao" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="vigente" {{ old('situacao', 'vigente') == 'vigente' ? 'selected' : '' }}>Vigente</option>
                    <option value="encerrado" {{ old('situacao') == 'encerrado' ? 'selected' : '' }}>Encerrado</option>
                    <option value="cancelado" {{ old('situacao') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Início <span class="text-red-500">*</span>
                </label>
                <input type="date" name="data_inicio" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('data_inicio') }}">
                @error('data_inicio')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Fim
                </label>
                <input type="date" name="data_fim"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('data_fim') }}">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Valor Total <span class="text-red-500">*</span>
                </label>
                <input type="number" name="valor_total" required step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('valor_total') }}" placeholder="0.00">
                @error('valor_total')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                          placeholder="Observações adicionais...">{{ old('observacoes') }}</textarea>
            </div>
        </div>

        <div class="mt-8 flex gap-4 pt-6 border-t border-gray-200">
            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                <x-heroicon-o-check class="w-5 h-5" />
                Salvar Contrato
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
