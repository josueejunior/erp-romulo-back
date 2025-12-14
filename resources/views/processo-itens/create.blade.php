@extends('layouts.app')

@section('title', 'Novo Item - ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Novo Item</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('processo-itens.store', $processo) }}">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Número do Item <span class="text-red-500">*</span>
                </label>
                <input type="number" name="numero_item" required min="1"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('numero_item', $proximoNumero) }}">
                @error('numero_item')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Quantidade <span class="text-red-500">*</span>
                </label>
                <input type="number" name="quantidade" required step="0.01" min="0.01"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('quantidade') }}" placeholder="0.00">
                @error('quantidade')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Unidade de Medida <span class="text-red-500">*</span>
                </label>
                <input type="text" name="unidade" required maxlength="50"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('unidade') }}" placeholder="Ex: UN, KG, M, M², etc">
                @error('unidade')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Valor Estimado
                </label>
                <input type="number" name="valor_estimado" step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('valor_estimado') }}" placeholder="0.00">
                @error('valor_estimado')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Especificação Técnica <span class="text-red-500">*</span>
                </label>
                <textarea name="especificacao_tecnica" required rows="5"
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                          placeholder="Descreva detalhadamente a especificação técnica do item...">{{ old('especificacao_tecnica') }}</textarea>
                @error('especificacao_tecnica')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Marca/Modelo de Referência
                </label>
                <input type="text" name="marca_modelo_referencia" maxlength="255"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('marca_modelo_referencia') }}" placeholder="Ex: Marca X, Modelo Y">
                @error('marca_modelo_referencia')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="flex items-center gap-2 mt-8">
                    <input type="checkbox" name="exige_atestado" value="1" 
                           {{ old('exige_atestado') ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           id="exige_atestado">
                    <span class="text-sm font-semibold text-gray-700">Exige Atestado de Capacidade Técnica</span>
                </label>
            </div>

            <div id="quantidade_atestado_field" style="display: none;">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Quantidade Mínima no Atestado
                </label>
                <input type="number" name="quantidade_minima_atestado" min="1"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('quantidade_minima_atestado') }}" placeholder="Quantidade mínima">
                @error('quantidade_minima_atestado')
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
                Salvar Item
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
document.getElementById('exige_atestado').addEventListener('change', function() {
    const field = document.getElementById('quantidade_atestado_field');
    if (this.checked) {
        field.style.display = 'block';
        field.querySelector('input').required = true;
    } else {
        field.style.display = 'none';
        field.querySelector('input').required = false;
        field.querySelector('input').value = '';
    }
});

// Verificar estado inicial
if (document.getElementById('exige_atestado').checked) {
    document.getElementById('quantidade_atestado_field').style.display = 'block';
}
</script>
@endsection
