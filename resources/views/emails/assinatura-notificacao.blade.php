<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isNovaAssinatura ? 'Assinatura Criada' : 'Assinatura Atualizada' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #3b82f6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .details {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .details ul {
            list-style: none;
            padding: 0;
        }
        .details li {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .details li:last-child {
            border-bottom: none;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>@if($isNovaAssinatura)🎉 Assinatura Criada com Sucesso!@else📝 Assinatura Atualizada@endif</h1>
    </div>
    
    <div class="content">
        <p>Olá,</p>
        
        @if($isNovaAssinatura)
        <p>Informamos que uma nova assinatura foi criada para a empresa <strong>{{ $empresa['razao_social'] ?? 'Sua Empresa' }}</strong>.</p>
        @else
        <p>Informamos que sua assinatura foi atualizada.</p>
        @endif
        
        <div class="details">
            <h2>Dados da Empresa:</h2>
            <ul>
                <li><strong>Razão Social:</strong> {{ $empresa['razao_social'] ?? 'N/A' }}</li>
                @if($empresa['nome_fantasia'] ?? null)
                <li><strong>Nome Fantasia:</strong> {{ $empresa['nome_fantasia'] }}</li>
                @endif
                @if($empresa['cnpj'] ?? null)
                <li><strong>CNPJ:</strong> {{ $empresa['cnpj'] }}</li>
                @endif
                @if($empresa['email'] ?? null)
                <li><strong>E-mail:</strong> {{ $empresa['email'] }}</li>
                @endif
                @if($empresa['telefone'] ?? null)
                <li><strong>Telefone:</strong> {{ $empresa['telefone'] }}</li>
                @endif
                @if(($empresa['logradouro'] ?? null) || ($empresa['cidade'] ?? null))
                <li><strong>Endereço:</strong> 
                    @if($empresa['logradouro'] ?? null){{ $empresa['logradouro'] }}@endif
                    @if($empresa['numero'] ?? null), {{ $empresa['numero'] }}@endif
                    @if($empresa['bairro'] ?? null) - {{ $empresa['bairro'] }}@endif
                    @if($empresa['cidade'] ?? null), {{ $empresa['cidade'] }}/{{ $empresa['estado'] ?? '' }}@endif
                    @if($empresa['cep'] ?? null) - CEP: {{ $empresa['cep'] }}@endif
                </li>
                @endif
            </ul>
        </div>
        
        <div class="details">
            <h2>Detalhes da Assinatura:</h2>
            <ul>
                <li><strong>Plano:</strong> {{ $plano['nome'] ?? 'N/A' }}</li>
                <li><strong>Status:</strong> {{ ucfirst($assinatura['status'] ?? 'ativa') }}</li>
                <li><strong>Valor:</strong> {{ isset($assinatura['valor_pago']) ? 'R$ ' . number_format((float)$assinatura['valor_pago'], 2, ',', '.') : 'Gratuito' }}</li>
                <li><strong>Método de Pagamento:</strong> {{ ucfirst(str_replace('_', ' ', $assinatura['metodo_pagamento'] ?? 'gratuito')) }}</li>
                @if(isset($assinatura['data_inicio']))
                <li><strong>Data de Início:</strong> {{ \Carbon\Carbon::parse($assinatura['data_inicio'])->format('d/m/Y') }}</li>
                @endif
                @if(isset($assinatura['data_fim']))
                <li><strong>Data de Vencimento:</strong> {{ \Carbon\Carbon::parse($assinatura['data_fim'])->format('d/m/Y') }}</li>
                @endif
                @if(isset($assinatura['dias_grace_period']))
                <li><strong>Período de Graça:</strong> {{ $assinatura['dias_grace_period'] }} dias</li>
                @endif
            </ul>
        </div>
        
        @if($isNovaAssinatura)
        <p>Sua assinatura está ativa e você já pode utilizar todos os recursos do plano contratado.</p>
        
        @if(($assinatura['status'] ?? 'ativa') === 'ativa')
        <p style="font-size: 16px; font-weight: bold; color: #059669; margin: 20px 0;">🎉 Aproveite o sistema!</p>
        @elseif(($assinatura['status'] ?? '') === 'pendente')
        <p>Aguardando confirmação do pagamento. Você receberá uma notificação quando for aprovado.</p>
        @endif
        @else
        <p>As alterações em sua assinatura já estão em vigor.</p>
        @endif
        
        <div class="footer">
            <p>Atenciosamente,<br>A equipe do Sistema ERP</p>
        </div>
    </div>
</body>
</html>