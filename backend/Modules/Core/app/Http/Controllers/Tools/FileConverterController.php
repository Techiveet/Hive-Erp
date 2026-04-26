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

    /**
     * Converts documents using Gotenberg LibreOffice engine.
     * Handles: DOCX→PDF, EPUB→PDF, image→PDF, HEIC→PDF
     */
    public function documentConvert(Request $request)
    {
        $request->validate([
            'mode'    => 'required|string|in:jpg-to-pdf,png-to-pdf,docx-to-pdf,epub-to-pdf,heic-to-pdf,pdf-to-epub,pdf-to-word',
            'files.*' => 'required|file',
        ]);

        $mode = $request->input('mode');
        $gotenbergUrl = env('GOTENBERG_URL', 'http://gotenberg:3000');

        try {
            $http = Http::asMultipart();
            $outputFilename = 'converted_' . time();
            $outputMime = 'application/pdf';

            if (in_array($mode, ['docx-to-pdf', 'epub-to-pdf', 'heic-to-pdf', 'jpg-to-pdf', 'png-to-pdf'])) {
                // LibreOffice conversion
                foreach ($request->file('files') as $file) {
                    $http->attach('files', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                }

                $response = $http->post("{$gotenbergUrl}/forms/libreoffice/convert");

                if (!$response->successful()) {
                    Log::error('Gotenberg LibreOffice Error: ' . $response->body());
                    return response()->json(['error' => 'Conversion failed.', 'details' => $response->body()], 500);
                }

                $inputName = $request->file('files')[0]->getClientOriginalName();
                $outputFilename = pathinfo($inputName, PATHINFO_FILENAME) . '.pdf';
                $outputMime = 'application/pdf';

            } elseif ($mode === 'pdf-to-word') {
                // Use LibreOffice to convert PDF → DOCX
                foreach ($request->file('files') as $file) {
                    $http->attach('files', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                }
                $http->attach('nativePageRanges', '');

                $response = $http->post("{$gotenbergUrl}/forms/libreoffice/convert", [
                    'convertTo' => 'docx',
                ]);

                if (!$response->successful()) {
                    Log::error('Gotenberg PDF→DOCX Error: ' . $response->body());
                    return response()->json(['error' => 'Conversion failed.', 'details' => $response->body()], 500);
                }

                $inputName = $request->file('files')[0]->getClientOriginalName();
                $outputFilename = pathinfo($inputName, PATHINFO_FILENAME) . '.docx';
                $outputMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

            } elseif ($mode === 'pdf-to-epub') {
                // EPUB conversion via LibreOffice
                foreach ($request->file('files') as $file) {
                    $http->attach('files', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                }

                $response = $http->post("{$gotenbergUrl}/forms/libreoffice/convert", [
                    'convertTo' => 'epub',
                ]);

                if (!$response->successful()) {
                    Log::error('Gotenberg PDF→EPUB Error: ' . $response->body());
                    return response()->json(['error' => 'Conversion failed.', 'details' => $response->body()], 500);
                }

                $inputName = $request->file('files')[0]->getClientOriginalName();
                $outputFilename = pathinfo($inputName, PATHINFO_FILENAME) . '.epub';
                $outputMime = 'application/epub+zip';
            }

            $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

            if (function_exists('activity')) {
                activity('File Conversion')
                    ->causedBy($request->user())
                    ->tap(function ($activity) use ($tenantId) { $activity->tenant_id = $tenantId; })
                    ->log("Converted document via Gotenberg ({$mode}).");
            }

            return response()->streamDownload(function () use ($response) {
                echo $response->body();
            }, $outputFilename, [
                'Content-Type' => $outputMime,
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);

        } catch (\Exception $e) {
            Log::error('Document Conversion Exception: ' . $e->getMessage());
            return response()->json([
                'error' => 'Conversion service is currently unreachable.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Converts video/audio files using the FFmpeg API Docker service.
     *
     * Endpoints proxied:
     *   POST /convert/media        → video/audio format conversion
     *   POST /convert/media/gif    → video → optimised GIF
     *   POST /convert/media/thumb  → video thumbnail extraction
     *   POST /convert/media/audio  → extract audio from video
     */
    public function mediaConvert(Request $request)
    {
        $request->validate([
            'file'          => 'required|file|max:512000', // 500 MB
            'action'        => 'required|string|in:convert,gif,thumbnail,extract-audio',
            'output_format' => 'nullable|string',
            'mode'          => 'nullable|string|in:video,audio',
            'quality'       => 'nullable|integer|min:1|max:100',
            'fps'           => 'nullable|integer|min:1|max:60',
            'width'         => 'nullable|integer|min:120|max:3840',
            'start'         => 'nullable|numeric|min:0',
            'duration'      => 'nullable|integer|min:1|max:300',
            'timestamp'     => 'nullable|string',
            'bitrate'       => 'nullable|string',
        ]);

        $ffmpegUrl = env('FFMPEG_URL', 'http://ffmpeg:9090');
        $action    = $request->input('action');
        $file      = $request->file('file');

        try {
            $http = Http::timeout(600)->asMultipart();
            $http->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName());

            // Forward all form fields except 'file' and 'action'
            $forwardFields = ['output_format', 'mode', 'quality', 'fps', 'width', 'start', 'duration', 'timestamp', 'bitrate'];
            foreach ($forwardFields as $field) {
                if ($request->filled($field)) {
                    $http->attach($field, (string) $request->input($field));
                }
            }

            $endpointMap = [
                'convert'       => '/convert',
                'gif'           => '/gif',
                'thumbnail'     => '/thumbnail',
                'extract-audio' => '/extract-audio',
            ];

            $response = $http->post($ffmpegUrl . $endpointMap[$action]);

            if (!$response->successful()) {
                Log::error("FFmpeg API Error [{$action}]: " . $response->body());
                return response()->json([
                    'error'   => 'Media conversion failed.',
                    'details' => $response->json('error') ?? $response->body(),
                ], $response->status());
            }

            // Extract filename from Content-Disposition or build one
            $disposition = $response->header('Content-Disposition') ?? '';
            preg_match('/filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)/', $disposition, $matches);
            $outputFilename = isset($matches[1])
                ? trim($matches[1], '"\'')
                : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.' . ($request->input('output_format', 'mp4'));

            $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
            if (function_exists('activity')) {
                activity('Media Conversion')
                    ->causedBy($request->user())
                    ->tap(fn($a) => $a->tenant_id = $tenantId)
                    ->log("Converted media via FFmpeg ({$action} → {$outputFilename}).");
            }

            return response()->streamDownload(function () use ($response) {
                echo $response->body();
            }, $outputFilename, [
                'Content-Type'                 => $response->header('Content-Type') ?? 'application/octet-stream',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);

        } catch (\Exception $e) {
            Log::error('FFmpeg API Exception: ' . $e->getMessage());
            return response()->json([
                'error'   => 'FFmpeg conversion service is unreachable.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
