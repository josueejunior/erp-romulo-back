@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard</h1>
    <p class="text-gray-600">Visão geral do sistema</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="card p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Em Participação</p>
                <p class="text-3xl font-bold text-blue-600">{{ $processosParticipacao }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-document-text class="w-5 h-5 text-blue-600" />
            </div>
        </div>
    </div>
    <div class="card p-6 border-l-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Em Julgamento</p>
                <p class="text-3xl font-bold text-yellow-600">{{ $processosJulgamento }}</p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-yellow-600" />
            </div>
        </div>
    </div>
    <div class="card p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Em Execução</p>
                <p class="text-3xl font-bold text-green-600">{{ $processosExecucao }}</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Próximas Disputas</h2>
            <a href="{{ route('calendario.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Ver todas
            </a>
        </div>
        @if($proximasDisputas->count() > 0)
        <div class="space-y-4">
            @foreach($proximasDisputas as $processo)
            <div class="border-l-4 border-blue-500 pl-4 py-2 hover:bg-gray-50 rounded-r-lg transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">{{ $processo->numero_modalidade }}</h3>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $processo->objeto_resumido }}</p>
                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-calendar class="w-4 h-4" />
                                {{ $processo->data_hora_sessao_publica->format('d/m/Y H:i') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-building-office class="w-4 h-4" />
                                {{ $processo->orgao->razao_social }}
                            </span>
                        </div>
                    </div>
                    <a href="{{ route('processos.show', $processo) }}" class="ml-4 text-blue-600 hover:text-blue-800">
                        <x-heroicon-o-chevron-right class="w-5 h-5" />
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8">
            <x-heroicon-o-calendar class="w-8 h-8 text-gray-400 mx-auto mb-3" />
            <p class="text-gray-500">Nenhuma disputa agendada</p>
        </div>
        @endif
    </div>

    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Documentos Vencendo</h2>
            <a href="{{ route('documentos-habilitacao.index', ['vencendo' => 1]) }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Ver todos
            </a>
        </div>
        @if($documentosVencendo->count() > 0)
        <div class="space-y-4">
            @foreach($documentosVencendo as $doc)
            <div class="border-l-4 border-yellow-500 pl-4 py-2 hover:bg-gray-50 rounded-r-lg transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">{{ $doc->tipo }}</h3>
                        @if($doc->numero)
                        <p class="text-sm text-gray-600 mt-1">{{ $doc->numero }}</p>
                        @endif
                        <div class="flex items-center gap-2 mt-2">
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                Vence em: {{ $doc->data_validade->format('d/m/Y') }}
                            </span>
                            @if($doc->data_validade->diffInDays(now()) <= 7)
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                Urgente
                            </span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('documentos-habilitacao.show', $doc) }}" class="ml-4 text-blue-600 hover:text-blue-800">
                        <x-heroicon-o-chevron-right class="w-5 h-5" />
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8">
            <x-heroicon-o-check-circle class="w-8 h-8 text-gray-400 mx-auto mb-3" />
            <p class="text-gray-500">Nenhum documento vencendo</p>
        </div>
        @endif
    </div>
</div>
@endsection
