<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 80px 25px 50px 25px; /* Top, Right, Bottom, Left */
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #334155;
        }
        /* Repeating Header */
        header {
            position: fixed;
            top: -60px;
            left: 0px;
            right: 0px;
            height: 40px;
            border-bottom: 2px solid #6366f1; /* Indigo Accent */
        }
        .brand {
            font-size: 16px;
            font-weight: 900;
            color: #1e1b4b;
            letter-spacing: 1.5px;
            margin: 0;
        }
        .report-meta {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8px;
            color: #64748b;
            margin-top: 4px;
            text-transform: uppercase;
        }
        /* Repeating Footer */
        footer {
            position: fixed;
            bottom: -35px;
            left: 0px;
            right: 0px;
            height: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .footer-table {
            width: 100%;
            border: none;
            margin: 0;
        }
        .footer-table td {
            border: none;
            padding: 0;
            font-size: 8px;
            color: #94a3b8;
        }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* Main Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th {
            background-color: #f8fafc;
            color: #475569;
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #fdfdfd;
        }
        .status-active { color: #059669; font-weight: bold; }
        .status-inactive { color: #dc2626; font-weight: bold; }
        .font-bold { font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>
    <header>
        <h1 class="brand">HIVE.OS :: {{ strtoupper($title) }}</h1>
        <div class="report-meta">REPORT_TYPE: USER_REGISTRY | GENERATED: {{ now()->format('Y-m-d H:i:s T') }}</div>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td class="text-left">HIVE.OS Enterprise Resource Planning - Internal System Audit</td>
                <td class="text-right">
                    <script type="text/php">
                        if (isset($pdf)) {
                            $x = $pdf->get_width() - 85;
                            $y = $pdf->get_height() - 25;
                            $text = "PAGE {PAGE_NUM} OF {PAGE_COUNT}";
                            $font = $fontMetrics->get_font("Helvetica", "normal");
                            $size = 7;
                            $color = array(0.58, 0.63, 0.72); // #94a3b8
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
                    <th width="5%">#</th>
                    <th width="25%">Operator Name</th>
                    <th width="30%">System Email</th>
                    <th width="15%">Role</th>
                    <th width="10%">Status</th>
                    <th width="15%">Provisioned</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $index => $user)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="font-bold">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->first()?->name ?? 'Member' }}</td>
                        <td class="{{ $user->is_active ? 'status-active' : 'status-inactive' }}">
                            {{ $user->is_active ? 'ACTIVE' : 'LOCKED' }}
                        </td>
                        <td>{{ $user->created_at->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
