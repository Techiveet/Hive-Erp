<?php

namespace Modules\Core\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileConverterController extends Controller
{
    /**
     * Converts ANY raw HTML or uploaded .html file into a PDF using Gotenberg Chromium.
     */
    public function htmlToPdf(Request $request)
    {
        $rules = [
            'paper_size'       => 'nullable|string|in:a4,letter,legal',
            'orientation'      => 'nullable|string|in:portrait,landscape',
            'margins'          => 'nullable|numeric|min:0',
            'print_background' => 'nullable|in:true,false,1,0',
            'assets.*'         => 'nullable|file',
        ];

        if ($request->hasFile('file')) {
            $rules['file'] = 'required|file|mimetypes:text/html';
        } else {
            $rules['html_content'] = 'required|string';
        }

        $request->validate($rules);

        try {
            $htmlContent = $request->hasFile('file')
                ? file_get_contents($request->file('file')->getRealPath())
                : $request->input('html_content');

            // 🚀 UNIVERSAL PRINT NORMALIZER
            // This safely resets browser defaults and prevents images/tables from
            // splitting awkwardly across multiple pages, without breaking user layouts.
            $universalPrintCss = "
            <style>
                @media print {
                    html, body {
                        margin: 0;
                        padding: 0;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    img, table, figure, blockquote {
                        page-break-inside: avoid;
                    }
                }
            </style>";

            if (stripos($htmlContent, '</head>') !== false) {
                $htmlContent = str_ireplace('</head>', $universalPrintCss . '</head>', $htmlContent);
            } else {
                $htmlContent = $universalPrintCss . $htmlContent;
            }

            $paperSize = $request->input('paper_size', 'a4');
            $orientation = $request->input('orientation', 'portrait');
            $margin = (float) $request->input('margins', 0);
            $printBackground = filter_var($request->input('print_background', 'true'), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

            $paperWidth = 8.27; // A4
            $paperHeight = 11.7; // A4

            if ($paperSize === 'letter') {
                $paperWidth = 8.5;
                $paperHeight = 11.0;
            } elseif ($paperSize === 'legal') {
                $paperWidth = 8.5;
                $paperHeight = 14.0;
            }

            $landscape = $orientation === 'landscape' ? 'true' : 'false';
            $gotenbergUrl = env('GOTENBERG_URL', 'http://gotenberg:3000');

            $http = Http::asMultipart();
            $http->attach('files', $htmlContent, 'index.html');

            if ($request->hasFile('assets')) {
                foreach ($request->file('assets') as $asset) {
                    $http->attach('files', file_get_contents($asset->getRealPath()), $asset->getClientOriginalName());
                }
            }

            $response = $http->post("{$gotenbergUrl}/forms/chromium/convert/html", [
                'paperWidth'        => $paperWidth,
                'paperHeight'       => $paperHeight,
                'landscape'         => $landscape,
                'marginTop'         => $margin,
                'marginBottom'      => $margin,
                'marginLeft'        => $margin,
                'marginRight'       => $margin,
                'printBackground'   => $printBackground,
                'preferCssPageSize' => 'true',
                'waitDelay'         => '1.5s',
            ]);

            if (!$response->successful()) {
                Log::error('Gotenberg Conversion Failed: ' . $response->body());
                return response()->json([
                    'error' => 'Failed to convert document via Gotenberg.',
                    'details' => $response->body()
                ], 500);
            }

            $filename = $request->hasFile('file')
                ? pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME) . '.pdf'
                : 'converted_document_' . time() . '.pdf';

            $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

            // Only log if you are actually tracking this activity
            if (function_exists('activity')) {
                activity('File Conversion')
                    ->causedBy($request->user())
                    ->tap(function($activity) use ($tenantId) {
                        $activity->tenant_id = $tenantId;
                    })
                    ->log("Converted HTML to PDF via Gotenberg.");
            }

            return response()->streamDownload(function () use ($response) {
                echo $response->body();
            }, $filename, [
                'Content-Type' => 'application/pdf',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);

        } catch (\Exception $e) {
            Log::error('Gotenberg Exception: ' . $e->getMessage());
            return response()->json([
                'error' => 'Conversion service is currently unreachable.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
