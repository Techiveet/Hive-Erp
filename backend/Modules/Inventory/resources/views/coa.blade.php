<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        .container {
            padding: 40px;
        }
        .header {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .header-content {
            position: relative;
            z-index: 10;
        }
        .title {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.025em;
            margin: 0;
            color: #0f172a;
        }
        .subtitle {
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 4px;
        }
        .batch-ref {
            margin-top: 15px;
            font-size: 14px;
        }
        .batch-number {
            color: #3b82f6;
            font-weight: 700;
        }
        .summary-grid {
            display: table;
            width: 100%;
            margin-top: 30px;
            border-collapse: separate;
            border-spacing: 15px 0;
        }
        .summary-card {
            display: table-cell;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 20px;
            width: 50%;
        }
        .card-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
            margin-bottom: 5px;
        }
        .card-value {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: -0.05em;
            color: #0f172a;
        }
        .status-banner {
            margin-top: 30px;
            padding: 20px;
            border-radius: 15px;
            border: 1px solid;
        }
        .status-passed {
            background-color: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }
        .status-failed {
            background-color: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .status-title {
            font-size: 18px;
            font-weight: 900;
            margin: 0;
        }
        .status-desc {
            font-size: 12px;
            margin-top: 2px;
            opacity: 0.8;
        }
        .details-section {
            margin-top: 40px;
        }
        .section-title {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #94a3b8;
            margin-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 10px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        .results-table th {
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .results-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .test-name {
            font-weight: 700;
            color: #0f172a;
        }
        .test-stage {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .test-value {
            font-family: monospace;
            font-weight: 700;
        }
        .value-passed {
            color: #059669;
        }
        .value-failed {
            color: #dc2626;
        }
        .metadata-section {
            margin-top: 30px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 20px;
        }
        .metadata-grid {
            display: table;
            width: 100%;
        }
        .metadata-item {
            display: table-cell;
            width: 50%;
        }
        .metadata-label {
            font-weight: 700;
            color: #0f172a;
            font-size: 12px;
        }
        .metadata-value {
            font-size: 12px;
            color: #64748b;
        }
        .notes-section {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        .notes-label {
            font-weight: 700;
            color: #0f172a;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .notes-content {
            font-size: 12px;
            color: #64748b;
            white-space: pre-wrap;
        }
        .footer {
            position: fixed;
            bottom: 40px;
            left: 40px;
            right: 40px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1 class="title">Certificate of Analysis</h1>
            <p class="subtitle">Quality Compliance Report</p>
            
            <div class="batch-ref">
                Batch Reference: <span class="batch-number">#{{ $coa['batch']['batch_number'] }}</span>
                @if($coa['batch']['product_name'])
                    <br>
                    <span style="color: #64748b;">{{ $coa['batch']['product_name'] }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="container">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="card-label">Compliance Score</div>
                <div class="card-value" style="color: #3b82f6;">{{ $coa['compliance']['score'] }}%</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Tests Conducted</div>
                <div class="card-value">
                    {{ $coa['compliance']['passed_tests'] }} <span style="font-size: 16px; color: #94a3b8; font-weight: 400;">/ {{ $coa['compliance']['total_tests'] }}</span>
                </div>
            </div>
        </div>

        <div class="status-banner {{ $coa['batch']['qa_status'] === 'qa_passed' ? 'status-passed' : 'status-failed' }}">
            @if($coa['batch']['qa_status'] === 'qa_passed')
                <h2 class="status-title">Released for Dispatch</h2>
                <p class="status-desc">This batch meets all microbiological and chemical safety standards.</p>
            @else
                <h2 class="status-title">Batch Quarantined</h2>
                <p class="status-desc">Compliance failure detected. Batch cannot be released for sale.</p>
            @endif
        </div>

        <div class="metadata-section">
            <div class="metadata-grid">
                <div class="metadata-item">
                    <p><span class="metadata-label">Tested By:</span> <span class="metadata-value">{{ $coa['tested_by'] ?: '-' }}</span></p>
                    <p><span class="metadata-label">Tested At:</span> <span class="metadata-value">{{ $coa['tested_at'] ? date('M d, Y h:i A', strtotime($coa['tested_at'])) : '-' }}</span></p>
                </div>
                <div class="metadata-item">
                    <p><span class="metadata-label">Sample Size:</span> <span class="metadata-value">{{ $coa['sample_size'] ?: '-' }}</span></p>
                    <p><span class="metadata-label">Release Decision:</span> <span class="metadata-value">{{ strtoupper($coa['batch']['release_decision']) }}</span></p>
                </div>
            </div>
            @if($coa['notes'])
                <div class="notes-section">
                    <div class="notes-label">Lab Notes</div>
                    <div class="notes-content">{{ $coa['notes'] }}</div>
                </div>
            @endif
        </div>

        <div class="details-section">
            <h3 class="section-title">Detailed Analysis</h3>
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Test Parameter</th>
                        <th style="width: 30%;">Result</th>
                        <th style="width: 20%; text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($coa['results'] as $result)
                        <tr>
                            <td>
                                <div class="test-name">{{ $result['test_name'] }}</div>
                                @if($result['stage_label'])
                                    <div class="test-stage">{{ $result['stage_label'] }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="test-value {{ $result['is_passed'] ? 'value-passed' : 'value-failed' }}">
                                    {{ $result['test_value'] }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                @if($result['is_passed'])
                                    <span style="color: #10b981; font-weight: 700; font-size: 11px; text-transform: uppercase;">Pass</span>
                                @else
                                    <span style="color: #ef4444; font-weight: 700; font-size: 11px; text-transform: uppercase;">Fail</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        Generated by Hive.OS Intelligence Engine | &copy; {{ date('Y') }} Techiveet. All rights reserved. | Confidential Document
    </div>
</body>
</html>
