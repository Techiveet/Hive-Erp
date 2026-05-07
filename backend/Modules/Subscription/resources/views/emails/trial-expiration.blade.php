<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background-color: #f6f9fc; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
        .content { padding: 30px; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .danger { background: #f8d7da; border: 1px solid #dc3545; padding: 15px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">HIVE.OS</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">Trial Subscription Notice</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $tenantName }},</h2>
            
            @if($isExpired)
                <div class="danger">
                    <strong>Your trial has expired!</strong>
                    <p style="margin: 10px 0 0;">Your {{ ucfirst($tenant->plan) }} trial period has ended. Upgrade now to keep access to your workspace and data.</p>
                </div>
            @elseif($daysRemaining <= 3)
                <div class="warning">
                    <strong>Urgent: Trial expiring soon!</strong>
                    <p style="margin: 10px 0 0;">Your trial expires in <strong>{{ $daysRemaining }} day(s)</strong>. Don't lose access to your workspace!</p>
                </div>
            @else
                <p>Your <strong>{{ ucfirst($tenant->plan) }}</strong> trial expires in <strong>{{ $daysRemaining }} days</strong>.</p>
            @endif
            
            <p>We hope you've enjoyed exploring Hive.OS. To continue using all the features without interruption, please upgrade your subscription.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $renewUrl }}" class="button">Upgrade Subscription</a>
            </div>
            
            <h3 style="font-size: 16px; margin-top: 30px;">What happens if you don't upgrade?</h3>
            <ul style="color: #666; line-height: 1.8;">
                <li>Your workspace will enter grace period for 7 days</li>
                <li>After grace period, your data will be archived</li>
                <li>You may lose access to premium features</li>
            </ul>
            
            <p style="color: #666; font-size: 14px;">Questions? Reply to this email or contact our support team.</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Hive.OS - All rights reserved.</p>
            <p>This email was sent to the administrator of {{ $tenantName }} workspace.</p>
        </div>
    </div>
</body>
</html>
