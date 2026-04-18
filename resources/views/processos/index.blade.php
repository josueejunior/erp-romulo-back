@extends('layouts.app')

@section('title', 'Processos')

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Processos</h1>
        <p class="text-gray-600">Gerencie todos os processos licitatórios</p>
    </div>
    <a href="{{ route('processos.create') }}" class="btn-primary text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 shadow-lg">
        <x-heroicon-o-plus class="w-5 h-5" />
        Novo Processo
    </a>
</div>

<div class="card overflow-hidden mb-6">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
        <form method="GET" class="flex gap-4 flex-wrap">
            <div class="flex-1 min-w-[250px]">
                <input type="text" name="search" placeholder="Buscar processos..." 
                       value="{{ request('search') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <select name="status" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos os status</option>
                <option value="participacao" {{ request('status') == 'participacao' ? 'selected' : '' }}>Participação</option>
                <option value="julgamento_habilitacao" {{ request('status') == 'julgamento_habilitacao' ? 'selected' : '' }}>Julgamento</option>
                <option value="execucao" {{ request('status') == 'execucao' ? 'selected' : '' }}>Execução</option>
                <option value="arquivado" {{ request('status') == 'arquivado' ? 'selected' : '' }}>Arquivado</option>
            </select>
            <button type="submit" class="bg-gray-700 text-white px-6 py-2 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                Filtrar
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Número</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Órgão</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Objeto</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Sessão</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($processos as $processo)
                <tr class="table-row transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">{{ $processo->numero_modalidade }}</div>
                        <div class="text-xs text-gray-500">{{ ucfirst($processo->modalidade) }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $processo->orgao->razao_social }}</div>
                        <div class="text-xs text-gray-500">{{ $processo->setor->nome }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 max-w-md">{{ Str::limit($processo->objeto_resumido, 60) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $processo->data_hora_sessao_publica->format('d/m/Y') }}</div>
                        <div class="text-xs text-gray-500">{{ $processo->data_hora_sessao_publica->format('H:i') }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php
                            $statusColors = [
                                'participacao' => 'bg-blue-100 text-blue-800',
                                'julgamento_habilitacao' => 'bg-yellow-100 text-yellow-800',
                                'execucao' => 'bg-green-100 text-green-800',
                                'vencido' => 'bg-purple-100 text-purple-800',
                                'perdido' => 'bg-red-100 text-red-800',
                                'arquivado' => 'bg-gray-100 text-gray-800',
                            ];
                            $color = $statusColors[$processo->status] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $color }}">
                            {{ ucfirst(str_replace('_', ' ', $processo->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('processos.show', $processo) }}" 
                           class="text-blue-600 hover:text-blue-900 font-medium inline-flex items-center gap-1">
                            Ver
                            <x-heroicon-o-chevron-right class="w-4 h-4" />
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                        <x-heroicon-o-document-text class="w-8 h-8 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-500 text-lg mb-2">Nenhum processo encontrado</p>
                        <a href="{{ route('processos.create') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                            Criar primeiro processo
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 bg-gray-50 border-t">
        {{ $processos->links() }}
    </div>
</div>
@endsection
