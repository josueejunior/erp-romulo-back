<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ERP Licitações')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar a {
            color: #cbd5e1;
            transition: all 0.2s ease;
            border-radius: 8px;
            margin-bottom: 4px;
        }
        .sidebar a:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            transform: translateX(4px);
        }
        .sidebar a.active {
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .table-row:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body class="bg-gray-50">
    @auth
    <div class="flex min-h-screen">
        <aside class="sidebar w-64 p-6 fixed h-full">
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-document-text class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-white text-lg font-bold">ERP Licitações</h1>
                        <p class="text-gray-400 text-xs">Sistema de Gestão</p>
                    </div>
                </div>
                @if(auth()->user()->empresaAtiva)
                <div class="mt-4 p-3 bg-white/10 rounded-lg border border-white/20">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()->empresaAtiva->razao_social }}</p>
                    <p class="text-gray-400 text-xs mt-1">{{ auth()->user()->empresaAtiva->cnpj ?? 'CNPJ não informado' }}</p>
                </div>
                @endif
            </div>
            
            <nav class="space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <x-heroicon-o-home class="w-5 h-5" />
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('processos.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('processos.*') ? 'active' : '' }}">
                    <x-heroicon-o-document-text class="w-5 h-5" />
                    <span>Processos</span>
                </a>
                <a href="{{ route('calendario.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('calendario.*') ? 'active' : '' }}">
                    <x-heroicon-o-calendar class="w-5 h-5" />
                    <span>Calendário</span>
                </a>
                <a href="{{ route('fornecedores.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('fornecedores.*') ? 'active' : '' }}">
                    <x-heroicon-o-building-office class="w-5 h-5" />
                    <span>Fornecedores</span>
                </a>
                <a href="{{ route('documentos-habilitacao.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('documentos-habilitacao.*') ? 'active' : '' }}">
                    <x-heroicon-o-document class="w-5 h-5" />
                    <span>Documentos</span>
                </a>
                <a href="{{ route('orgaos.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('orgaos.*') ? 'active' : '' }}">
                    <x-heroicon-o-building-office-2 class="w-5 h-5" />
                    <span>Órgãos</span>
                </a>
                <a href="{{ route('relatorios.financeiro') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('relatorios.*') ? 'active' : '' }}">
                    <x-heroicon-o-chart-bar class="w-5 h-5" />
                    <span>Relatórios</span>
                </a>
                @if(auth()->user()->empresas->count() > 1)
                <a href="{{ route('empresas.selecionar') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('empresas.selecionar') ? 'active' : '' }}">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                    <span>Trocar Empresa</span>
                </a>
                @endif
            </nav>

            <div class="mt-auto pt-8 border-t border-gray-700">
                <div class="px-4 py-3 mb-3">
                    <p class="text-gray-400 text-xs mb-1">Usuário</p>
                    <p class="text-white text-sm font-medium">{{ auth()->user()->name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full px-4 py-3 rounded text-gray-400 hover:bg-red-500/10 hover:text-red-400 transition-all">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5" />
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="flex-1 ml-64 p-8">
            @if(session('success'))
            <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 mr-3" />
                    <p class="text-green-800 font-medium">{{ session('success') }}</p>
                </div>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-6 p-4 bg-gradient-to-r from-red-50 to-rose-50 border-l-4 border-red-500 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <x-heroicon-s-x-circle class="w-5 h-5 text-red-500 mr-3" />
                    <p class="text-red-800 font-medium">{{ session('error') }}</p>
                </div>
            </div>
            @endif

            @auth
            @php
                $empresa = auth()->user()->empresaAtiva;
                $documentosUrgentes = $empresa ? $empresa->documentosHabilitacao()
                    ->whereNotNull('data_validade')
                    ->where('data_validade', '>=', now())
                    ->where('data_validade', '<=', now()->addDays(7))
                    ->count() : 0;
                $documentosVencidos = $empresa ? $empresa->documentosHabilitacao()
                    ->whereNotNull('data_validade')
                    ->where('data_validade', '<', now())
                    ->count() : 0;
            @endphp
            @if($documentosUrgentes > 0 || $documentosVencidos > 0)
            <div class="mb-6 p-4 bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-500 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-yellow-600 mr-3" />
                        <div>
                            <p class="text-yellow-800 font-semibold">Alertas de Documentos</p>
                            <p class="text-yellow-700 text-sm">
                                @if($documentosVencidos > 0)
                                    <span class="font-bold">{{ $documentosVencidos }} documento(s) vencido(s)</span>
                                    @if($documentosUrgentes > 0) e @endif
                                @endif
                                @if($documentosUrgentes > 0)
                                    <span class="font-bold">{{ $documentosUrgentes }} documento(s) vencendo em até 7 dias</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('documentos-habilitacao.index', ['vencendo' => 1]) }}" 
                       class="text-yellow-800 hover:text-yellow-900 font-medium text-sm underline">
                        Ver documentos
                    </a>
                </div>
            </div>
            @endif
            @endauth

            @yield('content')
        </main>
    </div>
    @else
        @yield('content')
    @endauth
</body>
</html>
