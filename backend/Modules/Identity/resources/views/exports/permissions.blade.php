<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 80px 25px 50px 25px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #334155; }

        header { position: fixed; top: -60px; left: 0px; right: 0px; height: 40px; border-bottom: 2px solid #2563eb; /* Blue Accent */ }
        .brand { font-size: 16px; font-weight: 900; color: #1e1b4b; letter-spacing: 1.5px; margin: 0; }
        .report-meta { font-family: 'Courier New', Courier, monospace; font-size: 8px; color: #64748b; margin-top: 4px; text-transform: uppercase; }

        footer { position: fixed; bottom: -35px; left: 0px; right: 0px; height: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .footer-table { width: 100%; border: none; margin: 0; }
        .footer-table td { border: none; padding: 0; font-size: 8px; color: #94a3b8; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background-color: #eff6ff; color: #1e40af; text-align: left; padding: 8px; border-bottom: 2px solid #bfdbfe; text-transform: uppercase; font-size: 8px; letter-spacing: 0.5px; }
        .data-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .data-table tr:nth-child(even) { background-color: #fdfdfd; }

        .code { font-family: 'Courier New', Courier, monospace; font-weight: bold; color: #2563eb; background: #eff6ff; padding: 3px 6px; border-radius: 4px; font-size: 9px; }
        .scope-badge { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; background: #f8fafc; color: #475569; }
    </style>
</head>
<body>
    <header>
        <h1 class="brand">HIVE.OS :: {{ strtoupper($title) }}</h1>
        <div class="report-meta">REPORT_TYPE: CAPABILITY_DICTIONARY | GENERATED: {{ now()->format('Y-m-d H:i:s T') }}</div>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td class="text-left">HIVE.OS Enterprise Resource Planning - System Architecture Dictionary</td>
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
                    <th width="35%">Capability Code</th>
                    <th width="45%">Context / Description</th>
                    <th width="15%">Scope</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $perm)
                    <tr>
                        <td>{{ $perm->id }}</td>
                        <td><span class="code">{{ $perm->name }}</span></td>
                        <td>Allows operator to {{ ucwords(str_replace('_', ' ', $perm->name)) }}</td>
                        <td><span class="scope-badge">{{ $perm->guard_name === 'tenant' ? 'Tenant Node' : 'Central Command' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
