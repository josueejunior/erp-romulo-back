<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinição de Senha - Sistema ERP</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #334155;
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            max-width: 560px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 32px 28px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 14px;
            opacity: 0.95;
        }
        .content {
            padding: 32px 28px;
        }
        .content p {
            margin: 0 0 16px;
            font-size: 15px;
            color: #475569;
        }
        .content p:first-child {
            color: #1e293b;
            font-size: 16px;
        }
        .cta-wrap {
            text-align: center;
            margin: 28px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
        }
        .btn:hover {
            opacity: 0.95;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #2563eb;
            padding: 16px 18px;
            border-radius: 0 8px 8px 0;
            margin: 24px 0;
            font-size: 14px;
            color: #475569;
        }
        .info-box strong {
            color: #1e40af;
        }
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 24px 0;
        }
        .footer {
            padding: 20px 28px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            border-top: 1px solid #f1f5f9;
        }
        .footer p {
            margin: 0;
        }
        .signature {
            margin-top: 24px;
            color: #475569;
            font-size: 15px;
        }
        .signature strong {
            color: #1e293b;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>🔐 Redefinição de Senha</h1>
                <p>Sistema ERP - Gestão de Licitações</p>
            </div>

            <div class="content">
                <p><strong>Olá!</strong></p>
                <p>Você está recebendo este e-mail porque foi solicitada a redefinição de senha da sua conta. Clique no botão abaixo para criar uma nova senha.</p>

                <div class="cta-wrap">
                    <a href="{{ $resetUrl }}" class="btn">Redefinir Senha</a>
                </div>

                <div class="info-box">
                    <strong>⏱ Link válido por 60 minutos.</strong><br>
                    Após esse período, será necessário solicitar uma nova redefinição.
                </div>

                <div class="divider"></div>

                <p style="font-size: 14px; color: #64748b;">Se você não solicitou a redefinição de senha, ignore este e-mail. Sua senha atual permanecerá inalterada.</p>

                <p class="signature">
                    Atenciosamente,<br>
                    <strong>Equipe Addsimp - Sistema ERP</strong>
                </p>
            </div>

            <div class="footer">
                <p>Este é um e-mail automático. Por favor, não responda.</p>
                <p>© {{ date('Y') }} Addsimp. Todos os direitos reservados.</p>
            </div>
        </div>
    </div>
</body>
</html>
