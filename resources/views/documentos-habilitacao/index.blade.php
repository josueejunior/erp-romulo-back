@extends('layouts.app')

@section('title', 'Documentos de Habilitação')

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Documentos de Habilitação</h1>
        <p class="text-gray-600">Biblioteca de documentos reutilizáveis</p>
    </div>
    <a href="{{ route('documentos-habilitacao.create') }}" class="btn-primary text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 shadow-lg">
        <x-heroicon-o-plus class="w-5 h-5" />
        Novo Documento
    </a>
</div>

<div class="card overflow-hidden mb-6">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
        <form method="GET" class="flex gap-4 flex-wrap">
            <div class="flex-1 min-w-[250px]">
                <input type="text" name="search" placeholder="Buscar por tipo, número ou identificação..." 
                       value="{{ request('search') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <a href="{{ route('documentos-habilitacao.index', ['vencendo' => 1]) }}" 
               class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors font-medium">
                Vencendo
            </a>
            <a href="{{ route('documentos-habilitacao.index', ['vencido' => 1]) }}" 
               class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors font-medium">
                Vencidos
            </a>
            @if(request('search') || request('vencendo') || request('vencido'))
            <a href="{{ route('documentos-habilitacao.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition-colors font-medium">
                Limpar
            </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Número</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Data Emissão</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Data Validade</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Arquivo</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($documentos as $documento)
                <tr class="table-row transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">{{ $documento->tipo }}</div>
                        @if($documento->identificacao)
                        <div class="text-xs text-gray-500">{{ $documento->identificacao }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">{{ $documento->numero ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">
                            {{ $documento->data_emissao ? $documento->data_emissao->format('d/m/Y') : '-' }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">
                            {{ $documento->data_validade ? $documento->data_validade->format('d/m/Y') : '-' }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($documento->data_validade)
                            @if($documento->isVencido())
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Vencido</span>
                            @elseif($documento->isVencendoEm(30))
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Vencendo</span>
                            @else
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Válido</span>
                            @endif
                        @else
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Sem validade</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($documento->arquivo)
                        <a href="{{ asset('storage/documentos/' . $documento->arquivo) }}" target="_blank" 
                           class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-900 text-sm font-medium">
                            <x-heroicon-o-eye class="w-4 h-4" />
                            Ver
                        </a>
                        @else
                        <span class="text-gray-400 text-sm">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('documentos-habilitacao.show', $documento) }}" class="text-blue-600 hover:text-blue-900 font-medium">Ver</a>
                            <a href="{{ route('documentos-habilitacao.edit', $documento) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</a>
                            <form method="POST" action="{{ route('documentos-habilitacao.destroy', $documento) }}" class="inline" 
                                  onsubmit="return confirm('Tem certeza que deseja excluir este documento?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <x-heroicon-o-document class="w-8 h-8 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-500 text-lg mb-2">Nenhum documento encontrado</p>
                        <a href="{{ route('documentos-habilitacao.create') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                            Cadastrar primeiro documento
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 bg-gray-50 border-t">
        {{ $documentos->links() }}
    </div>
</div>
@endsection
