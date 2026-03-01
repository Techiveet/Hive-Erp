<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'System Notification' }}</title>
    <style>
        /* Base Reset */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: #374151;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f4f5;
            padding: 40px 20px;
            box-sizing: border-box;
        }
        .main-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Header / Logo */
        .header {
            background-color: #ea580c;
            background-image: linear-gradient(135deg, #4f46e5 0%, #ea580c 100%);
            padding: 36px 24px;
            text-align: center;
        }
        .header-logo-text {
            margin: 0;
            color: #ffffff;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        .header-logo-img {
            max-height: 48px;
            width: auto;
            display: block;
            margin: 0 auto;
        }

        /* Body Content */
        .body-content {
            padding: 40px 36px;
            font-size: 16px;
            line-height: 1.6;
        }
        .title {
            color: #111827;
            font-size: 24px;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
        }
        .text-bold {
            color: #111827;
            font-weight: 600;
        }

        /* Password / Code Block */
        .data-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
        }
        .code-block {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 20px;
            border-radius: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 24px;
            text-align: center;
            letter-spacing: 4px;
            margin: 0 0 24px 0;
            color: #0f172a;
            font-weight: 700;
        }

        /* Call to Action Button */
        .btn-container {
            text-align: center;
            margin: 36px 0 24px 0;
        }
        .button {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff !important;
            padding: 16px 36px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.3);
            transition: all 0.2s ease;
        }

        /* Ledger / Changes Box */
        .changes-box {
            background-color: #f9fafb;
            border-left: 4px solid #4f46e5;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin-top: 32px;
        }
        .changes-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4f46e5;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .changes-list {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
            color: #475569;
        }
        .changes-list li {
            margin-bottom: 8px;
        }
        .changes-list li:last-child {
            margin-bottom: 0;
        }
        .change-key {
            font-weight: 600;
            color: #1e293b;
        }

        /* Sign Off */
        .sign-off {
            margin-top: 48px;
            font-size: 15px;
            color: #475569;
        }

        /* Footer */
        .footer {
            padding: 24px 36px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .footer-links {
            margin-top: 12px;
        }
        .footer-links a {
            color: #4f46e5;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-container">
            
            <div class="header">
                @if(!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $appName ?? 'HIVE.OS' }}" class="header-logo-img">
                @else
                    <h1 class="header-logo-text">{{ $appName ?? 'HIVE.OS' }}</h1>
                @endif
            </div>

            <div class="body-content">
                <h2 class="title">{{ $title ?? 'System Notification' }}</h2>
                <p>Hello <span class="text-bold">{{ $user->name ?? 'Operator' }}</span>,</p>
                <p>{{ $message_intro ?? 'A new system event requires your attention.' }}</p>

                @if(($type ?? '') === 'created')
                    <div style="margin-top: 36px;">
                        <div class="data-label">Temporary Encryption Key</div>
                        <div class="code-block">
                            {{ $rawPassword ?? '********' }}
                        </div>
                        <p style="font-size: 14px; color: #6b7280; text-align: center; margin-top: -8px;">
                            Securely store this key. You will be required to change it upon your first login.
                        </p>
                    </div>
                @endif

                @if(($type ?? '') === 'updated' && !empty($changes))
                    <div class="changes-box">
                        <div class="changes-title">Ledger: Modified Attributes</div>
                        <ul class="changes-list">
                            @foreach($changes as $key => $value)
                                <li>
                                    <span class="change-key">{{ $key }}</span> changed to: 
                                    <span style="color: #ea580c; font-weight: 700; margin-left: 4px;">{{ $value }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($actionUrl))
                    <div class="btn-container">
                        <a href="{{ $actionUrl }}" class="button">{{ $actionText ?? 'Continue' }}</a>
                    </div>
                @endif

                <div class="sign-off">
                    Securely,<br>
                    <span class="text-bold">The {{ $appName ?? 'Hive.OS' }} Central Command</span>
                </div>
            </div>

            <div class="footer">
                <p style="margin: 0 0 8px 0;">This is an automated system notification from the network.<br>Please do not reply directly to this transmission.</p>
                <p style="margin: 0;">&copy; {{ date('Y') }} {{ $appName ?? 'Hive Systems' }}. All rights reserved.</p>
            </div>

        </div>
    </div>
</body>
</html>