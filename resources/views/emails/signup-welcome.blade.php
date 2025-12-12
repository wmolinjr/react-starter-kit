<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('signup.email.welcome_subject', ['app_name' => $appName]) }}</title>
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
        .plan-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            background-color: #dbeafe;
            color: #1e40af;
        }
        .checklist {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .checklist li {
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
        }
        .checklist li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 {{ __('signup.email.welcome_title') }}</h1>
        </div>

        <div class="content">
            <p>{{ __('signup.email.greeting', ['name' => $customerName]) }}</p>

            <p>{{ __('signup.email.workspace_created') }}</p>

            <div class="highlight">
                <p style="margin: 0;">
                    <strong>{{ __('signup.email.workspace_name') }}:</strong> {{ $tenantName }}<br>
                    <strong>{{ __('signup.email.plan') }}:</strong>
                    <span class="plan-badge">{{ $planName }}</span>
                </p>
            </div>

            <p>{{ __('signup.email.access_workspace') }}</p>

            <div class="button-container">
                <a href="{{ $tenantUrl }}" class="button">
                    {{ __('signup.email.access_button') }}
                </a>
            </div>

            <p><strong>{{ __('signup.email.next_steps_title') }}</strong></p>
            <ul class="checklist">
                <li>{{ __('signup.email.next_step_1') }}</li>
                <li>{{ __('signup.email.next_step_2') }}</li>
                <li>{{ __('signup.email.next_step_3') }}</li>
            </ul>

            <p style="font-size: 14px; color: #6b7280; margin-top: 20px;">
                {{ __('signup.email.direct_link') }}<br>
                <span style="word-break: break-all;">{{ $tenantUrl }}</span>
            </p>
        </div>

        <div class="footer">
            <p>{{ __('signup.email.footer_thanks', ['app_name' => $appName]) }}</p>
            <p style="margin-top: 10px;">{{ __('signup.email.footer_support') }}</p>
        </div>
    </div>
</body>
</html>
