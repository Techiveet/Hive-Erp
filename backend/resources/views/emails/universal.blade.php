<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #ffffff; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #fb923c; padding: 20px; border-radius: 12px; }
        .logo { color: #fb923c; font-weight: bold; font-size: 24px; margin-bottom: 20px; }
        .btn { background: #fb923c; color: #000; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; margin-top: 20px; }
        .footer { margin-top: 40px; font-size: 12px; color: #666; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">HIVE.OS</div>
        <h2>{{ $title }}</h2>
        <p>Hello {{ $user->name }},</p>
        <p>{{ $message_intro }}</p>

        @if(isset($actionUrl))
            <a href="{{ $actionUrl }}" class="btn">INITIALIZE ACCESS</a>
        @endif

        @if(isset($changes) && count($changes) > 0)
            <div style="background: #1a1a1a; padding: 15px; border-radius: 8px; margin-top: 15px;">
                <p style="font-size: 12px; color: #fb923c;">MODIFIED DATA:</p>
                @foreach($changes as $key => $value)
                    <div style="font-family: monospace;">- {{ strtoupper($key) }}: UPDATED</div>
                @endforeach
            </div>
        @endif

        <div class="footer">
            NODE_ID: {{ gethostname() }}<br>
            TIMESTAMP: {{ now()->toDateTimeString() }}
        </div>
    </div>
</body>
</html>
