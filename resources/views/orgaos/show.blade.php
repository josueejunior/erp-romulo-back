@extends('layouts.app')

@section('title', 'Órgão: ' . $orgao->razao_social)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ $orgao->razao_social }}</h1>
        @if($orgao->uasg)
        <p class="text-gray-600 mt-1">UASG: {{ $orgao->uasg }}</p>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="{{ route('orgaos.edit', $orgao) }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Editar
        </a>
        <a href="{{ route('orgaos.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
            Voltar
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Informações Cadastrais</h2>
        <div class="space-y-3">
            @if($orgao->uasg)
            <div>
                <p class="text-sm text-gray-500">UASG</p>
                <p class="font-semibold">{{ $orgao->uasg }}</p>
            </div>
            @endif
            <div>
                <p class="text-sm text-gray-500">Razão Social</p>
                <p class="font-semibold">{{ $orgao->razao_social }}</p>
            </div>
            @if($orgao->cnpj)
            <div>
                <p class="text-sm text-gray-500">CNPJ</p>
                <p class="font-semibold">{{ $orgao->cnpj }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Contato</h2>
        <div class="space-y-3">
            @if($orgao->endereco)
            <div>
                <p class="text-sm text-gray-500">Endereço</p>
                <p class="font-semibold">{{ $orgao->endereco }}</p>
            </div>
            @endif
            @if($orgao->cidade || $orgao->estado)
            <div>
                <p class="text-sm text-gray-500">Cidade/Estado</p>
                <p class="font-semibold">
                    {{ $orgao->cidade }}{{ $orgao->cidade && $orgao->estado ? '/' : '' }}{{ $orgao->estado }}
                    @if($orgao->cep)
                        - {{ $orgao->cep }}
                    @endif
                </p>
            </div>
            @endif
            @if($orgao->telefone)
            <div>
                <p class="text-sm text-gray-500">Telefone</p>
                <p class="font-semibold">{{ $orgao->telefone }}</p>
            </div>
            @endif
            @if($orgao->email)
            <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="font-semibold">{{ $orgao->email }}</p>
            </div>
            @endif
        </div>
    </div>

    @if($orgao->observacoes)
    <div class="md:col-span-2 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Observações</h2>
        <p class="text-gray-700 whitespace-pre-line">{{ $orgao->observacoes }}</p>
    </div>
    @endif
</div>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Setores</h2>
        <a href="{{ route('setors.create', ['orgao_id' => $orgao->id]) }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            Novo Setor
        </a>
    </div>
    @if($orgao->setors->count() > 0)
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($orgao->setors as $setor)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $setor->nome }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-500">{{ $setor->email ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-500">{{ $setor->telefone ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('setors.edit', $setor) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                            <form method="POST" action="{{ route('setors.destroy', $setor) }}" class="inline" 
                                  onsubmit="return confirm('Tem certeza que deseja excluir este setor?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-gray-500">Nenhum setor cadastrado para este órgão.</p>
    @endif
</div>
@endsection
