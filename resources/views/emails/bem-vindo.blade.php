<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao Sistema ERP</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
        }
        .content {
            margin-bottom: 30px;
        }
        .status-box {
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .status-sucesso {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        .status-gratuito {
            background-color: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        .status-pendente {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .status-erro {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        ul li:before {
            content: "✅ ";
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Bem-vindo à Plataforma!</h1>
        </div>
        
        <div class="content">
            <p>Olá <strong>{{ $user['name'] ?? $user['nome'] ?? 'Usuário' }}</strong>,</p>
            
            <p>É um prazer tê-lo conosco no <strong>Sistema ERP - Gestão de Licitações</strong>!</p>
            
            <h2>Informações da sua conta</h2>
            <ul>
                <li><strong>Empresa:</strong> {{ $tenant['razao_social'] ?? 'N/A' }}</li>
                <li><strong>E-mail:</strong> {{ $user['email'] ?? 'N/A' }}</li>
            </ul>
            
            @if($assinatura)
            <h2>Status da sua Assinatura</h2>
            
            @if($statusCobranca && $statusCobranca['status'] === 'sucesso')
            <div class="status-box status-sucesso">
                <strong>✅ {{ $statusCobranca['mensagem'] }}</strong>
                <ul style="margin-top: 10px;">
                    <li><strong>Plano:</strong> {{ $assinatura->plano->nome ?? 'N/A' }}</li>
                    <li><strong>Status:</strong> Ativa</li>
                    <li><strong>Válida até:</strong> {{ $assinatura->data_fim ? \Carbon\Carbon::parse($assinatura->data_fim)->format('d/m/Y') : 'N/A' }}</li>
                </ul>
            </div>
            @elseif($statusCobranca && $statusCobranca['status'] === 'gratuito')
            <div class="status-box status-gratuito">
                <strong>ℹ️ {{ $statusCobranca['mensagem'] }}</strong>
                <ul style="margin-top: 10px;">
                    <li><strong>Plano:</strong> {{ $assinatura->plano->nome ?? 'Gratuito' }}</li>
                    <li><strong>Válida até:</strong> {{ $assinatura->data_fim ? \Carbon\Carbon::parse($assinatura->data_fim)->format('d/m/Y') : 'N/A' }}</li>
                </ul>
            </div>
            @elseif($statusCobranca && $statusCobranca['status'] === 'pendente')
            <div class="status-box status-pendente">
                <strong>⏳ {{ $statusCobranca['mensagem'] }}</strong>
                <ul style="margin-top: 10px;">
                    <li><strong>Plano:</strong> {{ $assinatura->plano->nome ?? 'N/A' }}</li>
                    <li><strong>Status:</strong> Pendente</li>
                </ul>
            </div>
            @elseif($statusCobranca && $statusCobranca['status'] === 'erro')
            <div class="status-box status-erro">
                <strong>⚠️ {{ $statusCobranca['mensagem'] }}</strong>
                <p style="margin-top: 10px;">Por favor, entre em contato com nosso suporte para regularizar sua situação.</p>
            </div>
            @endif
            
            @else
            <h2>Próximos Passos</h2>
            <p>Você ainda não possui uma assinatura ativa. Acesse o sistema para contratar um plano e começar a usar todas as funcionalidades.</p>
            @endif
            
            <h2>O que você pode fazer agora?</h2>
            <ul>
                <li>Gerenciar processos licitatórios</li>
                <li>Controlar orçamentos e fornecedores</li>
                <li>Acompanhar contratos e empenhos</li>
                <li>Gerar relatórios e análises</li>
                <li>E muito mais!</li>
            </ul>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ config('app.url') }}" class="button">Acessar o Sistema</a>
            </div>
            
            <p>Se você tiver alguma dúvida, nossa equipe de suporte está pronta para ajudar!</p>
            
            <p>
                Atenciosamente,<br>
                <strong>Equipe Sistema ERP - Gestão de Licitações</strong>
            </p>
        </div>
        
        <div class="footer">
            <p>Este é um e-mail automático, por favor não responda.</p>
            <p>© {{ date('Y') }} Sistema ERP - Gestão de Licitações. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>

