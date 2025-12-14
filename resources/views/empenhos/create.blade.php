@extends('layouts.app')

@section('title', 'Novo Empenho - ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Novo Empenho</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('empenhos.store', $processo) }}">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Número do Empenho <span class="text-red-500">*</span>
                </label>
                <input type="text" name="numero" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('numero') }}" placeholder="Número do empenho">
                @error('numero')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Data <span class="text-red-500">*</span>
                </label>
                <input type="date" name="data" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('data') }}">
                @error('data')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Valor <span class="text-red-500">*</span>
                </label>
                <input type="number" name="valor" required step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('valor') }}" placeholder="0.00">
                @error('valor')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Entrega
                </label>
                <input type="date" name="data_entrega"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('data_entrega') }}">
            </div>

            @if($contratos->count() > 0)
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Contrato (opcional)
                </label>
                <select name="contrato_id" id="contrato_id"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Nenhum (empenho direto ao processo)</option>
                    @foreach($contratos as $contrato)
                    <option value="{{ $contrato->id }}" {{ old('contrato_id') == $contrato->id ? 'selected' : '' }}>
                        {{ $contrato->numero }} - R$ {{ number_format($contrato->valor_total, 2, ',', '.') }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($autorizacoesFornecimento->count() > 0)
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Autorização de Fornecimento (opcional)
                </label>
                <select name="autorizacao_fornecimento_id" id="autorizacao_fornecimento_id"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Nenhuma (empenho direto ao processo)</option>
                    @foreach($autorizacoesFornecimento as $af)
                    <option value="{{ $af->id }}" {{ old('autorizacao_fornecimento_id') == $af->id ? 'selected' : '' }}>
                        {{ $af->numero }} - R$ {{ number_format($af->valor, 2, ',', '.') }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

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
                Salvar Empenho
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
