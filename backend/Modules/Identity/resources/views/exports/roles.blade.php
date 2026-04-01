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

        :root {
            --brand-header-color: {{ $branding['document_header_color'] ?? '#1E293B' }};
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
            border-bottom: 2px solid var(--brand-header-color);
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
            color: var(--brand-header-color);
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
            color: var(--brand-header-color);
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
            table-layout: fixed; /* Ensures wide permission strings wrap correctly */
        }

        .data-table th {
            background-color: var(--brand-header-color);
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 8px;
            text-align: left;
            border: 1px solid var(--brand-header-color);
        }

        .data-table td {
            padding: 8px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            word-wrap: break-word;
            line-height: 1.5;
        }

        /* Subtle zebra striping for readability */
        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* ----------------------------------------------------
           UI ELEMENTS
        ---------------------------------------------------- */
        .designation-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 10px;
            text-transform: uppercase;
        }

        .capabilities-text {
            font-family: 'Courier New', Courier, monospace;
            color: #475569;
            font-size: 8px;
            line-height: 1.6;
        }

        .god-mode {
            color: #dc2626;
            font-weight: bold;
            background-color: #fef2f2;
            padding: 2px 4px;
            border-radius: 2px;
            border: 1px solid #fecaca;
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
                    <p class="report-subtitle">Access Control & Authorization Matrix</p>
                </td>
                <td class="meta-td">
                    <span class="meta-label">Date Generated:</span> {{ now()->format('M d, Y - H:i:s T') }}<br>
                    <span class="meta-label">Total Matrix Roles:</span> {{ count($data) }}<br>
                    @if(!empty($branding['company_tax_id']))
                        <span class="meta-label">Tax ID:</span> {{ $branding['company_tax_id'] }}<br>
                    @endif
                    <span class="meta-label">Generated By:</span> {{ auth()->user()->name ?? 'System Engine' }}
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table class="footer-table">
            <tr>
                <td style="text-align: left; width: 33%;">{{ $branding['app_title'] ?? 'HIVE.OS' }}</td>
                <td style="text-align: center; width: 34%;">{{ !empty($branding['company_tax_id']) ? 'Tax ID: ' . $branding['company_tax_id'] : 'Strictly Confidential' }}</td>
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
                        <th width="20%">{{ $t('roles.col_designation', 'Clearance Designation') }}</th>
                        <th width="60%">{{ $t('roles.col_capabilities', 'Granted Capabilities') }}</th>
                        <th width="15%">{{ $t('roles.col_established', 'Established') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $role)
                    @php
                        $perms = $role->permissions->pluck('name')->implode(', ');
                        $isGodMode = false;

                        if ($role->name === 'Super Admin') {
                            $perms = $t('roles.god_mode', 'ALL PROTOCOLS (GOD MODE)');
                            $isGodMode = true;
                        }

                        if (empty($perms)) {
                            $perms = $t('roles.no_access', 'No Access Permissions Granted');
                        }
                    @endphp
                    <tr>
                        <td style="color: #94a3b8; font-weight: bold;">
                            {{ str_pad($loop->iteration, 4, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="designation-name">
                            {{ $role->name }}
                        </td>
                        <td class="capabilities-text">
                            @if($isGodMode)
                                <span class="god-mode">{{ $perms }}</span>
                            @else
                                {{ $perms }}
                            @endif
                        </td>
                        <td style="color: #64748b; font-weight: bold;">
                            {{ $role->created_at->format('Y-m-d') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">
                            No role matrix records found for the selected criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>
