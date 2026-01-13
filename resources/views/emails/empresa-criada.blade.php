<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Cadastrada com Sucesso</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .info-item {
            margin: 10px 0;
        }
        .info-label {
            font-weight: bold;
            color: #667eea;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Empresa Cadastrada com Sucesso!</h1>
    </div>
    
    <div class="content">
        <p>Olá,</p>
        
        <p>Sua empresa foi cadastrada com sucesso no sistema ERP - Gestão de Licitações.</p>
        
        <div class="info-box">
            <h3>Dados da Empresa</h3>
            <div class="info-item">
                <span class="info-label">Razão Social:</span> {{ $tenant['razao_social'] ?? 'N/A' }}
            </div>
            @if($tenant['cnpj'] ?? null)
            <div class="info-item">
                <span class="info-label">CNPJ:</span> {{ $tenant['cnpj'] }}
            </div>
            @endif
            @if($tenant['email'] ?? null)
            <div class="info-item">
                <span class="info-label">E-mail:</span> {{ $tenant['email'] }}
            </div>
            @endif
            @if($tenant['status'] ?? null)
            <div class="info-item">
                <span class="info-label">Status:</span> {{ ucfirst($tenant['status']) }}
            </div>
            @endif
        </div>
        
        <p><strong>Próximos Passos:</strong></p>
        <ul>
            <li>Um banco de dados separado foi criado para sua empresa</li>
            <li>Agora você pode criar usuários para acessar o sistema</li>
            <li>Entre em contato com o suporte para configurar sua assinatura</li>
        </ul>
        
        <p>Se você tiver alguma dúvida, não hesite em entrar em contato conosco.</p>
        
        <p>Atenciosamente,<br>
        <strong>Equipe Sistema ERP - Gestão de Licitações</strong></p>
    </div>
    
    <div class="footer">
        <p>Este é um email automático, por favor não responda.</p>
        <p>&copy; {{ date('Y') }} Sistema ERP - Gestão de Licitações. Todos os direitos reservados.</p>
    </div>
</body>
</html>






