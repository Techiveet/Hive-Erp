<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Demo Request</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background-color: #f6f9fc; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
        .content { padding: 30px; }
        .field { margin-bottom: 15px; }
        .label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .value { font-size: 14px; color: #333; font-weight: 500; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .message-box { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 6px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">HIVE.OS</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">New Demo Request</p>
        </div>
        
        <div class="content">
            <div class="field">
                <div class="label">Name</div>
                <div class="value">{{ $demoRequest->first_name }} {{ $demoRequest->last_name }}</div>
            </div>

            <div class="field">
                <div class="label">Email</div>
                <div class="value">{{ $demoRequest->email }}</div>
            </div>

            @if($demoRequest->phone)
            <div class="field">
                <div class="label">Phone</div>
                <div class="value">{{ $demoRequest->phone }}</div>
            </div>
            @endif

            <div class="field">
                <div class="label">Company</div>
                <div class="value">{{ $demoRequest->company }}</div>
            </div>

            @if($demoRequest->company_size)
            <div class="field">
                <div class="label">Company Size</div>
                <div class="value">{{ $demoRequest->company_size }}</div>
            </div>
            @endif

            @if($interests !== 'Not specified')
            <div class="field">
                <div class="label">Interested Modules</div>
                <div class="value">{{ $interests }}</div>
            </div>
            @endif

            @if($demoRequest->message)
            <div class="field">
                <div class="label">Message</div>
                <div class="message-box">{{ $demoRequest->message }}</div>
            </div>
            @endif

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $adminPanelUrl }}" class="button">View in Admin Panel</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} HIVE.OS - All rights reserved.</p>
            <p>This email was sent to notify you of a new demo request.</p>
        </div>
    </div>
</body>
</html>
