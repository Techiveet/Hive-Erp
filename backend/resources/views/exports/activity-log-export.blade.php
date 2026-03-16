<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 60px 25px 40px 25px; }
        body { font-family: sans-serif; font-size: 9px; color: #334155; }
        header { position: fixed; top: -40px; left: 0; right: 0; border-bottom: 2px solid #6366f1; padding-bottom: 5px; }
        .brand { font-size: 14px; font-weight: bold; color: #1e1b4b; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background: #f8fafc; padding: 6px; text-align: left; border-bottom: 1px solid #cbd5e1; text-transform: uppercase; font-size: 8px;}
        .data-table td { padding: 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .badge { font-weight: bold; font-size: 8px; }
    </style>
</head>
<body>
    <header>
        <div class="brand">HIVE.OS // {{ strtoupper($title) }}</div>
        <div style="font-size: 8px; color: #64748b;">Generated: {{ now()->format('Y-m-d H:i:s') }}</div>
    </header>
    <main>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="15%">Time (UTC)</th>
                    <th width="15%">Action</th>
                    <th width="35%">Description</th>
                    <th width="15%">Operator</th>
                    <th width="15%">Node</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $log)
                <tr>
                    <td>{{ $loop->iteration }}</td>

                    <td>{{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A' }}</td>
                    <td><span class="badge">{{ strtoupper($log->event ?? 'SYS') }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td><strong>{{ $log->causer ? $log->causer->name : 'SYSTEM' }}</strong></td>
                    <td>{{ strtoupper($log->tenant_id ?? 'CENTRAL') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
