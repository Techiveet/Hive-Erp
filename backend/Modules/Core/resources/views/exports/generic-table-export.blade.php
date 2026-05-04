<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 100px 30px 60px 30px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #334155;
            margin: 0;
            padding: 0;
        }

        :root {
            --brand-header-color: {{ $headerColor ?? '#1E293B' }};
        }

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
            color: #64748b;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.data-table th {
            background-color: var(--brand-header-color);
            color: white;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid var(--brand-header-color);
        }

        table.data-table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
        }

        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        table.data-table tr:hover {
            background-color: #f1f5f9;
        }

        .seq-col {
            width: 40px;
            text-align: center;
        }

        .key-col {
            width: 120px;
            font-family: monospace;
        }

        .label-col {
            width: 150px;
            font-weight: bold;
        }

        .desc-col {
            width: auto;
        }

        .icon-col {
            width: 80px;
            font-family: monospace;
            font-size: 8px;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            color: #64748b;
        }

        .footer-table td {
            padding: 0 10px;
        }

        .footer-left {
            text-align: left;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            text-align: right;
        }

        .page-number {
            font-size: 8px;
            color: #94a3b8;
        }

        .watermark {
            position: fixed;
            bottom: 20px;
            right: 30px;
            font-size: 10px;
            color: #cbd5e1;
            font-style: italic;
        }
    </style>
</head>
<body>
    <header>
        <table class="header-table">
            <tr>
                <td class="logo-td">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="logo-img" alt="Logo">
                    @else
                        <span style="font-size: 14px; font-weight: bold; color: {{ $headerColor ?? '#1E293B' }};">{{ $appTitle ?? 'HIVE.OS' }}</span>
                    @endif
                </td>
                <td class="title-td">
                    <p class="report-title">{{ $title }}</p>
                    <p class="report-subtitle">{{ $appTitle ?? 'HIVE.OS' }} - Business Types Export</p>
                </td>
                <td class="meta-td">
                    <div><strong>Generated:</strong> {{ $generatedAt }}</div>
                    <div><strong>Total Records:</strong> {{ count($rows) }}</div>
                    @if($companyTaxId)<div><strong>Tax ID:</strong> {{ $companyTaxId }}</div>@endif
                </td>
            </tr>
        </table>
    </header>

    <table class="data-table">
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $key => $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <footer>
        <table class="footer-table">
            <tr>
                <td class="footer-left">{{ $footerText ?? 'Powered by HIVE.OS' }}</td>
                <td class="footer-center">{{ $companyTaxId ? 'Tax ID: ' . $companyTaxId : 'Confidential' }}</td>
                <td class="footer-right">
                    <span class="page-number">Page <span class="page"></span></span>
                </td>
            </tr>
        </table>
    </footer>

    <div class="watermark">{{ $appTitle ?? 'HIVE.OS' }}</div>
</body>
</html>