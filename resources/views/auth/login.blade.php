@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="card p-8 shadow-2xl">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <x-heroicon-o-document-text class="w-6 h-6 text-white" />
                </div>
                <h2 class="text-3xl font-bold text-gray-900">
                    ERP Licitações
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Faça login para acessar o sistema
                </p>
            </div>
            
            <form class="space-y-6" method="POST" action="{{ route('login') }}">
                @csrf
                
                @if(session('error'))
                <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                    <div class="flex items-center">
                        <x-heroicon-s-x-circle class="w-5 h-5 text-red-500 mr-3" />
                        <p class="text-sm text-red-800">{{ session('error') }}</p>
                    </div>
                </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <x-heroicon-o-envelope class="h-5 w-5 text-gray-400" />
                            </div>
                            <input id="email" name="email" type="email" required autofocus
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                   placeholder="seu@email.com" value="{{ old('email') }}">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                                <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Senha
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <x-heroicon-o-lock-closed class="h-5 w-5 text-gray-400" />
                            </div>
                            <input id="password" name="password" type="password" required
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                   placeholder="••••••••">
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                                <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Lembrar-me
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                            class="w-full btn-primary text-white py-3 px-4 rounded-lg font-semibold text-base shadow-lg flex items-center justify-center gap-2">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5" />
                        Entrar
                    </button>
                </div>
            </form>
        </div>
        
        <p class="mt-6 text-center text-sm text-gray-600">
            Sistema de Gestão de Licitações
        </p>
    </div>
</div>
@endsection
