<?php
declare(strict_types=1);

require __DIR__ . '/../backend/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function resolvePath(string $path): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|\\\\\\\\|/)~', $path) === 1) {
        return $path;
    }

    return getcwd() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function defaultOutputPath(string $htmlPath): string
{
    $htmlName = strtolower(basename($htmlPath));

    if (str_contains($htmlName, 'amharic')) {
        return __DIR__ . '/HIVE_User_Access_Guide_Amharic.pdf';
    }

    return __DIR__ . '/HIVE_User_Access_Guide.pdf';
}

$htmlPath = isset($argv[1]) ? resolvePath($argv[1]) : __DIR__ . '/user-access-guide.html';
$outputPath = isset($argv[2]) ? resolvePath($argv[2]) : defaultOutputPath($htmlPath);

if (!is_file($htmlPath)) {
    fwrite(STDERR, "HTML source not found: {$htmlPath}\n");
    exit(1);
}

$html = file_get_contents($htmlPath);

if ($html === false) {
    fwrite(STDERR, "Failed to read HTML source.\n");
    exit(1);
}

$html = str_replace(
    ['{{GENERATED_DATE}}'],
    [date('F j, Y')],
    $html
);

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();
$directory = dirname($outputPath);
$filename = pathinfo($outputPath, PATHINFO_FILENAME);
$extension = pathinfo($outputPath, PATHINFO_EXTENSION) ?: 'pdf';
$outputCandidates = [
    $outputPath,
    $directory . DIRECTORY_SEPARATOR . $filename . '_Updated.' . $extension,
    $directory . DIRECTORY_SEPARATOR . $filename . '_' . date('Ymd_His') . '.' . $extension,
];
$writtenPath = null;

foreach ($outputCandidates as $candidate) {
    if (@file_put_contents($candidate, $pdfOutput) !== false) {
        $writtenPath = $candidate;
        break;
    }
}

if ($writtenPath === null) {
    fwrite(STDERR, "Failed to write PDF output. The primary file may be open or locked.\n");
    exit(1);
}

if ($writtenPath !== $outputPath) {
    fwrite(STDOUT, "Primary output was locked. Generated fallback PDF: {$writtenPath}\n");
    exit(0);
}

fwrite(STDOUT, "Generated PDF: {$writtenPath}\n");