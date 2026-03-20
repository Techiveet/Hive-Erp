<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 80px 25px 50px 25px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #334155; }

        header { position: fixed; top: -60px; left: 0px; right: 0px; height: 40px; border-bottom: 2px solid #ea580c; /* Amber Accent */ }
        .brand { font-size: 16px; font-weight: 900; color: #1e1b4b; letter-spacing: 1.5px; margin: 0; }
        .report-meta { font-family: 'Courier New', Courier, monospace; font-size: 8px; color: #64748b; margin-top: 4px; text-transform: uppercase; }

        footer { position: fixed; bottom: -35px; left: 0px; right: 0px; height: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .footer-table { width: 100%; border: none; margin: 0; }
        .footer-table td { border: none; padding: 0; font-size: 8px; color: #94a3b8; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background-color: #fff7ed; color: #9a3412; text-align: left; padding: 8px; border-bottom: 2px solid #fdba74; text-transform: uppercase; font-size: 8px; letter-spacing: 0.5px; }
        .data-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; line-height: 1.4; }
        .data-table tr:nth-child(even) { background-color: #fdfdfd; }

        .god-mode { color: #ea580c; font-weight: bold; background: #ffedd5; padding: 2px 6px; border-radius: 4px; font-size: 8px; }
        .no-access { color: #94a3b8; font-style: italic; font-size: 9px; }
        .font-bold { font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>
    <header>
        <h1 class="brand">HIVE.OS :: {{ strtoupper($title) }}</h1>
        <div class="report-meta">REPORT_TYPE: ACCESS_CONTROL_MATRIX | GENERATED: {{ now()->format('Y-m-d H:i:s T') }}</div>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td class="text-left">HIVE.OS Enterprise Resource Planning - Strictly Confidential Security Matrix</td>
                <td class="text-right">
                    <script type="text/php">
                        if (isset($pdf)) {
                            $x = $pdf->get_width() - 85;
                            $y = $pdf->get_height() - 25;
                            $text = "PAGE {PAGE_NUM} OF {PAGE_COUNT}";
                            $font = $fontMetrics->get_font("Helvetica", "normal");
                            $size = 7;
                            $color = array(0.58, 0.63, 0.72);
                            $pdf->page_text($x, $y, $text, $font, $size, $color);
                        }
                    </script>
                </td>
            </tr>
        </table>
    </footer>

    <main>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="20%">Clearance Designation</th>
                    <th width="60%">Network Capabilities</th>
                    <th width="15%">Established</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $role)
                    <tr>
                        <td>{{ $role->id }}</td>
                        <td class="font-bold">{{ $role->name }}</td>
                        <td>
                            @if($role->name === 'Super Admin')
                                <span class="god-mode">ALL PROTOCOLS (GOD MODE)</span>
                            @elseif($role->permissions->count() > 0)
                                {{ $role->permissions->pluck('name')->implode(', ') }}
                            @else
                                <span class="no-access">No Access Explicitly Assigned</span>
                            @endif
                        </td>
                        <td>{{ $role->created_at->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
