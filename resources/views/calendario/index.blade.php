@extends('layouts.app')

@section('title', 'Calendário de Disputas')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Calendário de Disputas</h1>
    <p class="text-gray-600">Acompanhe as próximas sessões públicas</p>
</div>

<div class="card overflow-hidden mb-6">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
        <form method="GET" class="flex gap-4 flex-wrap">
            <input type="date" name="data_inicio" value="{{ request('data_inicio') }}"
                   class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <input type="date" name="data_fim" value="{{ request('data_fim') }}"
                   class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="bg-gray-700 text-white px-6 py-2 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                Filtrar
            </button>
            @if(request('data_inicio') || request('data_fim'))
            <a href="{{ route('calendario.index') }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-medium">
                Limpar
            </a>
            @endif
        </form>
    </div>

    <div class="divide-y divide-gray-200">
        @forelse($processos as $processo)
        <div class="p-6 hover:bg-gray-50 transition-colors">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <h3 class="text-xl font-bold text-gray-900">{{ $processo->numero_modalidade }}</h3>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ ucfirst($processo->modalidade) }}
                        </span>
                    </div>
                    <p class="text-gray-700 mb-4">{{ $processo->objeto_resumido }}</p>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-4">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-building-office class="w-4 h-4" />
                            {{ $processo->orgao->razao_social }}
                        </span>
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-calendar class="w-4 h-4" />
                            {{ $processo->data_hora_sessao_publica->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    @if($processo->itens->count() > 0)
                    <div class="mt-4">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Preços Mínimos:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($processo->itens as $item)
                                @if($item->formacoesPreco->count() > 0)
                                    @php $precoMin = $item->formacoesPreco->first()->preco_minimo; @endphp
                                    <span class="px-3 py-1 bg-blue-50 text-blue-800 rounded-lg text-xs font-medium border border-blue-200">
                                        Item {{ $item->numero_item }}: R$ {{ number_format($precoMin, 2, ',', '.') }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                <a href="{{ route('processos.show', $processo) }}" 
                   class="ml-6 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium flex items-center gap-2">
                    Ver Detalhes
                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                </a>
            </div>
        </div>
        @empty
        <div class="p-12 text-center">
            <x-heroicon-o-calendar class="w-8 h-8 text-gray-400 mx-auto mb-4" />
            <p class="text-gray-500 text-lg">Nenhum processo encontrado no período selecionado</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
