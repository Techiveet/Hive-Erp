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
            vertical-align: middle;
            word-wrap: break-word;
            line-height: 1.5;
        }

        /* Subtle zebra striping for readability */
        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* ----------------------------------------------------
           UI ELEMENTS (Specifically requested styles)
        ---------------------------------------------------- */
        .code {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            color: #0f172a;
            background: #e2e8f0;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 9px;
            border: 1px solid #cbd5e1;
        }

        .scope-badge {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #c7d2fe;
            padding: 3px 6px;
            border-radius: 4px;
            background: #e0e7ff;
            color: #3730a3;
            font-weight: bold;
        }

        .desc-text {
            color: #475569;
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
                    <p class="report-subtitle">System Architecture Dictionary</p>
                </td>
                <td class="meta-td">
                    <span class="meta-label">Date Generated:</span> {{ now()->format('M d, Y - H:i:s T') }}<br>
                    <span class="meta-label">Total Capabilities:</span> {{ count($data) }}<br>
                    <span class="meta-label">Generated By:</span> {{ auth()->user()->name ?? 'System Engine' }}
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td style="text-align: left; width: 33%;">HIVE.OS Enterprise Resource Planning</td>
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
                        <th width="6%">Seq</th>
                        <th width="35%">{{ $t('permissions.col_code', 'Capability Code') }}</th>
                        <th width="44%">{{ $t('permissions.col_desc', 'Context / Description') }}</th>
                        <th width="15%">{{ $t('permissions.col_scope', 'Security Scope') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $perm)
                    <tr>
                        <td style="color: #94a3b8; font-weight: bold;">
                            {{ str_pad($loop->iteration, 4, '0', STR_PAD_LEFT) }}
                        </td>
                        <td>
                            <span class="code">{{ $perm->name }}</span>
                        </td>
                        <td class="desc-text">
                            {{ $t('permissions.allows_operator', 'Allows operator to') }} {{ ucwords(str_replace('_', ' ', $perm->name)) }}
                        </td>
                        <td>
                            <span class="scope-badge">
                                {{ $perm->guard_name === 'tenant' ? $t('permissions.tenant_node', 'Tenant Node') : $t('permissions.central', 'Central Command') }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">
                            No capabilities found for the selected criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>
