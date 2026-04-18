@extends('layouts.app')

@section('title', 'Fornecedores')

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Fornecedores</h1>
        <p class="text-gray-600">Gerencie seus fornecedores</p>
    </div>
    <a href="{{ route('fornecedores.create') }}" class="btn-primary text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 shadow-lg">
        <x-heroicon-o-plus class="w-5 h-5" />
        Novo Fornecedor
    </a>
</div>

<div class="card overflow-hidden mb-6">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
        <form method="GET" class="flex gap-4 flex-wrap">
            <div class="flex-1 min-w-[250px]">
                <input type="text" name="search" placeholder="Buscar por razão social, CNPJ ou nome fantasia..." 
                       value="{{ request('search') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" class="bg-gray-700 text-white px-6 py-2 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                Buscar
            </button>
            @if(request('search'))
            <a href="{{ route('fornecedores.index') }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-medium">
                Limpar
            </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Razão Social</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Nome Fantasia</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">CNPJ</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Cidade/Estado</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Contato</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($fornecedores as $fornecedor)
                <tr class="table-row transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">{{ $fornecedor->razao_social }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">{{ $fornecedor->nome_fantasia ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">{{ $fornecedor->cnpj ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">
                            @if($fornecedor->cidade || $fornecedor->estado)
                                {{ $fornecedor->cidade }}{{ $fornecedor->cidade && $fornecedor->estado ? '/' : '' }}{{ $fornecedor->estado }}
                            @else
                                -
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600">{{ $fornecedor->telefone ?? '-' }}</div>
                        @if($fornecedor->email)
                        <div class="text-xs text-gray-500">{{ $fornecedor->email }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('fornecedores.show', $fornecedor) }}" class="text-blue-600 hover:text-blue-900 font-medium">Ver</a>
                            <a href="{{ route('fornecedores.edit', $fornecedor) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</a>
                            <form method="POST" action="{{ route('fornecedores.destroy', $fornecedor) }}" class="inline" 
                                  onsubmit="return confirm('Tem certeza que deseja excluir este fornecedor?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 font-medium">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                        <x-heroicon-o-building-office class="w-8 h-8 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-500 text-lg mb-2">Nenhum fornecedor encontrado</p>
                        <a href="{{ route('fornecedores.create') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                            Cadastrar primeiro fornecedor
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 bg-gray-50 border-t">
        {{ $fornecedores->links() }}
    </div>
</div>
@endsection
