@extends('layouts.app')

@section('title', 'Registrar Disputa - ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Registrar Disputa</h1>
        <p class="text-gray-600 mt-1">{{ $processo->numero_modalidade }} - {{ $processo->data_hora_sessao_publica->format('d/m/Y H:i') }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('disputas.update', $processo) }}">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            @foreach($processo->itens as $index => $item)
            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Item {{ $item->numero_item }}</h3>
                    <p class="text-sm text-gray-700 mb-3">{{ $item->especificacao_tecnica }}</p>
                    <div class="flex gap-4 text-xs text-gray-600">
                        <span><strong>Quantidade:</strong> {{ $item->quantidade }} {{ $item->unidade }}</span>
                        @if($item->valor_estimado)
                        <span><strong>Valor Estimado:</strong> R$ {{ number_format($item->valor_estimado, 2, ',', '.') }}</span>
                        @endif
                    </div>
                </div>

                <input type="hidden" name="itens[{{ $index }}][id]" value="{{ $item->id }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Valor Final da Sessão
                        </label>
                        <input type="number" name="itens[{{ $index }}][valor_final_sessao]" step="0.01" min="0"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               value="{{ old("itens.{$index}.valor_final_sessao", $item->valor_final_sessao) }}" placeholder="0.00">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Classificação
                        </label>
                        <input type="number" name="itens[{{ $index }}][classificacao]" min="1"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               value="{{ old("itens.{$index}.classificacao", $item->classificacao) }}" placeholder="Posição na classificação">
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-8 flex gap-4 pt-6 border-t border-gray-200">
            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                <x-heroicon-o-check class="w-5 h-5" />
                Salvar Disputa
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
