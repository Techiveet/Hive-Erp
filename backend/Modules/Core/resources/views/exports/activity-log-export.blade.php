<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* ----------------------------------------------------
           GLOBAL SETUP & TYPOGRAPHY
        ---------------------------------------------------- */
        @page {
            margin: 110px 30px 60px 30px; /* Top margin accommodates the fixed header */
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #334155;
            margin: 0;
            padding: 0;
        }

        /* ----------------------------------------------------
           HEADER (Fixed to top of every page)
        ---------------------------------------------------- */
        header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 60px;
            border-bottom: 2px solid #1e293b; /* Deep slate corporate line */
            padding-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-td {
            width: 160px;
            vertical-align: middle;
        }

        .logo-img {
            max-height: 40px;
            width: auto;
            display: block;
        }

        .title-td {
            vertical-align: middle;
            text-align: left;
        }

        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 3px 0;
        }

        .report-subtitle {
            font-size: 9px;
            color: #64748b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-td {
            text-align: right;
            vertical-align: middle;
            font-size: 8px;
            color: #475569;
            line-height: 1.4;
        }

        .meta-label {
            font-weight: bold;
            color: #1e293b;
        }

        /* ----------------------------------------------------
           FOOTER (Fixed to bottom of every page)
        ---------------------------------------------------- */
        footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            color: #94a3b8;
        }

        .page-number:after {
            content: counter(page);
        }

        /* ----------------------------------------------------
           DATA TABLE
        ---------------------------------------------------- */
        .table-container {
            margin-top: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
        }

        .data-table th {
            background-color: #1e293b; /* Deep corporate blue/slate */
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 8px;
            text-align: left;
            border: 1px solid #1e293b;
        }

        .data-table td {
            padding: 8px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            word-wrap: break-word;
            line-height: 1.4;
        }

        /* Subtle zebra striping for readability */
        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* ----------------------------------------------------
           UI ELEMENTS
        ---------------------------------------------------- */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
            background-color: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            letter-spacing: 0.5px;
        }

        .operator-name {
            font-weight: bold;
            color: #0f172a;
        }

        .node-tag {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            color: #475569;
        }

        .timestamp {
            color: #64748b;
            font-size: 8px;
        }
    </style>
</head>
<body>

    <header>
        <table class="header-table">
            <tr>
                <td class="logo-td">
                    @if(!empty($logoUrl))
                        <img src="{{ $logoUrl }}" class="logo-img">
                    @endif
                </td>
                <td class="title-td">
                    <h1 class="report-title">{{ $title }}</h1>
                    <p class="report-subtitle">Official System Report</p>
                </td>
                <td class="meta-td">
                    <span class="meta-label">Date Generated:</span> {{ now()->format('M d, Y - H:i:s T') }}<br>
                    <span class="meta-label">Total Records:</span> {{ count($data) }}<br>
                    <span class="meta-label">Generated By:</span> {{ auth()->user()->name ?? 'System WORM Engine' }}
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td style="text-align: left; width: 33%;">HIVE.OS Enterprise Management</td>
                <td style="text-align: center; width: 34%;">Strictly Confidential</td>
                <td style="text-align: right; width: 33%;">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

    <main>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="5%">Seq</th>
                        <th width="14%">Timestamp (UTC)</th>
                        <th width="12%">Action</th>
                        <th width="39%">Event Description</th>
                        <th width="15%">Operator</th>
                        <th width="15%">Node Origin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $log)
                    <tr>
                        <td style="color: #94a3b8; font-weight: bold;">
                            {{ str_pad($loop->iteration, 4, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="timestamp">
                            {{ $log->created_at ? \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') : 'N/A' }}
                        </td>
                        <td>
                            <span class="badge">{{ strtoupper($log->event ?? 'SYS') }}</span>
                        </td>
                        <td style="color: #1e293b;">
                            {{ $log->description }}
                        </td>
                        <td>
                            <span class="operator-name">
                                {{ $log->causer ? $log->causer->name : ($log->properties['causer_name'] ?? 'SYSTEM') }}
                            </span>
                        </td>
                        <td>
                            <span class="node-tag">{{ strtoupper($log->tenant_id ?? 'CENTRAL') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">
                            No audit records found for the selected criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>
