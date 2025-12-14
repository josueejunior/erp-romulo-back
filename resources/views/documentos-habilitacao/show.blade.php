@extends('layouts.app')

@section('title', 'Documento: ' . $documentoHabilitacao->tipo)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ $documentoHabilitacao->tipo }}</h1>
        @if($documentoHabilitacao->identificacao)
        <p class="text-gray-600 mt-1">{{ $documentoHabilitacao->identificacao }}</p>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="{{ route('documentos-habilitacao.edit', $documentoHabilitacao) }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Editar
        </a>
        <a href="{{ route('documentos-habilitacao.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
            Voltar
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Informações do Documento</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Tipo</p>
                <p class="font-semibold">{{ $documentoHabilitacao->tipo }}</p>
            </div>
            @if($documentoHabilitacao->numero)
            <div>
                <p class="text-sm text-gray-500">Número</p>
                <p class="font-semibold">{{ $documentoHabilitacao->numero }}</p>
            </div>
            @endif
            @if($documentoHabilitacao->identificacao)
            <div>
                <p class="text-sm text-gray-500">Identificação</p>
                <p class="font-semibold">{{ $documentoHabilitacao->identificacao }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Validade</h2>
        <div class="space-y-3">
            @if($documentoHabilitacao->data_emissao)
            <div>
                <p class="text-sm text-gray-500">Data de Emissão</p>
                <p class="font-semibold">{{ $documentoHabilitacao->data_emissao->format('d/m/Y') }}</p>
            </div>
            @endif
            @if($documentoHabilitacao->data_validade)
            <div>
                <p class="text-sm text-gray-500">Data de Validade</p>
                <p class="font-semibold">{{ $documentoHabilitacao->data_validade->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                @if($documentoHabilitacao->isVencido())
                    <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800 font-semibold">Vencido</span>
                @elseif($documentoHabilitacao->isVencendoEm(30))
                    <span class="px-3 py-1 text-sm rounded-full bg-yellow-100 text-yellow-800 font-semibold">Vencendo em breve</span>
                @else
                    <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800 font-semibold">Válido</span>
                @endif
            </div>
            @else
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <span class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-800 font-semibold">Sem validade definida</span>
            </div>
            @endif
        </div>
    </div>

    @if($documentoHabilitacao->arquivo)
    <div class="md:col-span-2 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Arquivo</h2>
        <a href="{{ asset('storage/documentos/' . $documentoHabilitacao->arquivo) }}" target="_blank" 
           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            <x-heroicon-o-arrow-down-tray class="w-5 h-5 mr-2" />
            Abrir Arquivo
        </a>
    </div>
    @endif

    @if($documentoHabilitacao->observacoes)
    <div class="md:col-span-2 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Observações</h2>
        <p class="text-gray-700 whitespace-pre-line">{{ $documentoHabilitacao->observacoes }}</p>
    </div>
    @endif
</div>
@endsection
