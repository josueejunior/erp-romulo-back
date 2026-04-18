@extends('layouts.app')

@section('title', 'Fornecedor: ' . $fornecedor->razao_social)

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="{{ route('fornecedores.index') }}" class="text-gray-600 hover:text-gray-900">
            <x-heroicon-o-arrow-left class="w-6 h-6" />
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $fornecedor->razao_social }}</h1>
            @if($fornecedor->nome_fantasia)
            <p class="text-gray-600 mt-1">{{ $fornecedor->nome_fantasia }}</p>
            @endif
        </div>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('fornecedores.edit', $fornecedor) }}" 
           class="btn-primary text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-pencil class="w-5 h-5" />
            Editar
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-building-office class="w-5 h-5 text-blue-600" />
            </div>
            <h2 class="text-xl font-bold text-gray-900">Informações Cadastrais</h2>
        </div>
        <div class="space-y-4">
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">Razão Social</p>
                <p class="text-base font-semibold text-gray-900">{{ $fornecedor->razao_social }}</p>
            </div>
            @if($fornecedor->nome_fantasia)
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">Nome Fantasia</p>
                <p class="text-base font-semibold text-gray-900">{{ $fornecedor->nome_fantasia }}</p>
            </div>
            @endif
            @if($fornecedor->cnpj)
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">CNPJ</p>
                <p class="text-base font-semibold text-gray-900">{{ $fornecedor->cnpj }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="card p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-envelope class="w-5 h-5 text-green-600" />
            </div>
            <h2 class="text-xl font-bold text-gray-900">Contato</h2>
        </div>
        <div class="space-y-4">
            @if($fornecedor->endereco)
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">Endereço</p>
                <p class="text-base font-semibold text-gray-900">{{ $fornecedor->endereco }}</p>
            </div>
            @endif
            @if($fornecedor->cidade || $fornecedor->estado)
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">Cidade/Estado</p>
                <p class="text-base font-semibold text-gray-900">
                    {{ $fornecedor->cidade }}{{ $fornecedor->cidade && $fornecedor->estado ? '/' : '' }}{{ $fornecedor->estado }}
                    @if($fornecedor->cep)
                        - {{ $fornecedor->cep }}
                    @endif
                </p>
            </div>
            @endif
            @if($fornecedor->telefone)
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">Telefone</p>
                <p class="text-base font-semibold text-gray-900">{{ $fornecedor->telefone }}</p>
            </div>
            @endif
            @if($fornecedor->email)
            <div class="pb-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500 mb-1">Email</p>
                <a href="mailto:{{ $fornecedor->email }}" class="text-base font-semibold text-blue-600 hover:text-blue-800">
                    {{ $fornecedor->email }}
                </a>
            </div>
            @endif
            @if($fornecedor->contato)
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Contato</p>
                <p class="text-base font-semibold text-gray-900">{{ $fornecedor->contato }}</p>
            </div>
            @endif
        </div>
    </div>

    @if($fornecedor->observacoes)
    <div class="md:col-span-2 card p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-pencil class="w-5 h-5 text-yellow-600" />
            </div>
            <h2 class="text-xl font-bold text-gray-900">Observações</h2>
        </div>
        <p class="text-gray-700 whitespace-pre-line leading-relaxed">{{ $fornecedor->observacoes }}</p>
    </div>
    @endif
</div>
@endsection
