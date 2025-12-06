<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convite para {{ $tenant->name }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin-bottom: 15px;
        }
        .highlight {
            background-color: #eff6ff;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .highlight strong {
            color: #2563eb;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background-color: #2563eb;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .button-container {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }
        .role-admin {
            background-color: #fef3c7;
            color: #92400e;
        }
        .role-member {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .role-guest {
            background-color: #f3f4f6;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Você foi convidado!</h1>
        </div>

        <div class="content">
            <p>Olá,</p>

            <p><strong>{{ $invitedBy->name }}</strong> ({{ $invitedBy->email }}) convidou você para fazer parte da equipe <strong>{{ $tenant->name }}</strong>.</p>

            <div class="highlight">
                <p style="margin: 0;">
                    <strong>Organização:</strong> {{ $tenant->name }}<br>
                    <strong>Função:</strong>
                    <span class="role-badge role-{{ $role }}">
                        @if($role === 'admin')
                            Administrador
                        @elseif($role === 'member')
                            Membro
                        @elseif($role === 'guest')
                            Convidado
                        @endif
                    </span>
                </p>
            </div>

            <p>Para aceitar o convite e começar a colaborar com a equipe, clique no botão abaixo:</p>

            <div class="button-container">
                <a href="{{ $acceptUrl }}" class="button">
                    Aceitar Convite
                </a>
            </div>

            <p style="font-size: 14px; color: #6b7280; margin-top: 20px;">
                Se você não conseguir clicar no botão, copie e cole este link no seu navegador:<br>
                <span style="word-break: break-all;">{{ $acceptUrl }}</span>
            </p>
        </div>

        <div class="footer">
            <p>Este convite foi enviado por {{ config('app.name') }}.</p>
            <p style="margin-top: 10px;">Se você não esperava este convite, pode ignorar este e-mail com segurança.</p>
        </div>
    </div>
</body>
</html>
