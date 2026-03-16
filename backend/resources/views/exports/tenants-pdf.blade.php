<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* PDF Page Configuration */
        @page { margin: 80px 25px 50px 25px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 9px; color: #334155; }

        /* Global Header (Appears on every page) */
        header { position: fixed; top: -60px; left: 0px; right: 0px; height: 45px; border-bottom: 2px solid #ea580c; }
        .brand { font-size: 18px; font-weight: 900; color: #1e1b4b; letter-spacing: 1px; margin: 0; }
        .report-meta { font-family: 'Courier New', Courier, monospace; font-size: 7px; color: #64748b; margin-top: 4px; text-transform: uppercase; }

        /* Global Footer (Appears on every page) */
        footer { position: fixed; bottom: -35px; left: 0px; right: 0px; height: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .footer-table { width: 100%; border: none; margin: 0; }
        .footer-table td { border: none; padding: 0; font-size: 7px; color: #94a3b8; text-transform: uppercase; }

        /* Table Styling */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; table-layout: fixed; }
        .data-table th { background-color: #f8fafc; color: #475569; text-align: left; padding: 10px 8px; border-bottom: 2px solid #ea580c; text-transform: uppercase; font-size: 7px; letter-spacing: 0.5px; }
        .data-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; word-wrap: break-word; }
        .data-table tr:nth-child(even) { background-color: #fdfdfd; }

        /* Utility Classes */
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .status-online { color: #10b981; background: #ecfdf5; }
        .status-suspended { color: #ef4444; background: #fef2f2; }
        .plan-badge { color: #6366f1; background: #eef2ff; border: 1px solid #e0e7ff; }
        .font-mono { font-family: 'Courier New', Courier, monospace; font-weight: bold; color: #0f172a; }
        .text-bold { font-weight: bold; color: #1e1b4b; }
        .text-muted { color: #94a3b8; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <header>
        <h1 class="brand">HIVE.OS :: {{ strtoupper($title) }}</h1>
        <div class="report-meta">
            REGISTRY: TENANT_NODE_CLUSTER |
            TOTAL_NODES: {{ count($data) }} |
            GENERATED: {{ now()->format('Y-m-d H:i:s T') }}
        </div>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td>HIVE.OS Network Infrastructure Ledger - System Generated Report</td>
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
                    <th width="12%">Node ID</th>
                    <th width="20%">Organization</th>
                    <th width="10%">Plan</th>
                    <th width="20%">Routing Domain</th>
                    <th width="10%">Status</th>
                    <th width="18%">Root Admin</th>
                    <th width="10%">Provisioned</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $tenant)
                    <tr>
                        <td class="font-mono">{{ strtoupper($tenant->id) }}</td>

                        {{-- 🚀 THE FIX: Capitalizes the ID if the explicit Name field is missing --}}
                        <td class="text-bold">{{ $tenant->name ?? ucfirst($tenant->id) }}</td>

                        <td>
                            <span class="badge plan-badge">{{ $tenant->plan ?? 'Standard' }}</span>
                        </td>
                        <td class="text-muted">
                            {{ $tenant->domains->first()->domain ?? $tenant->id . '.localhost' }}
                        </td>
                        <td>
                            @if($tenant->is_active ?? true)
                                <span class="badge status-online">Online</span>
                            @else
                                <span class="badge status-suspended">Suspended</span>
                            @endif
                        </td>
                        <td>
                            <div style="font-size: 8px;">{{ $tenant->admin_email ?? 'NOT_SET' }}</div>
                            <div style="font-size: 6px; color: #94a3b8; margin-top: 2px;">
                                ADMIN: {{ ($tenant->admin_active ?? true) ? 'ACTIVE' : 'LOCKED' }}
                            </div>
                        </td>
                        <td class="text-muted">
                            {{ $tenant->created_at ? $tenant->created_at->format('Y-m-d') : 'N/A' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
