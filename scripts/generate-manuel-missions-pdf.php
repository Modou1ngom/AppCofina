<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

/**
 * Redimensionne et compresse une image pour l'inclusion PDF.
 *
 * @return array{mime: string, data: string}|null
 */
function encodeImageForPdf(string $file, int $maxWidth = 1000, int $jpegQuality = 82): ?array
{
    if (! extension_loaded('gd')) {
        $mime = mime_content_type($file) ?: 'image/png';

        return [
            'mime' => $mime,
            'data' => base64_encode((string) file_get_contents($file)),
        ];
    }

    $info = @getimagesize($file);
    if ($info === false) {
        return null;
    }

    [$width, $height, $type] = $info;
    $source = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
        default => false,
    };

    if ($source === false) {
        return null;
    }

    $targetWidth = min($width, $maxWidth);
    $targetHeight = (int) round($height * ($targetWidth / $width));
    $resized = imagecreatetruecolor($targetWidth, $targetHeight);

    if ($type === IMAGETYPE_PNG) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($source);

    ob_start();
    imagejpeg($resized, null, $jpegQuality);
    $binary = (string) ob_get_clean();
    imagedestroy($resized);

    return [
        'mime' => 'image/jpeg',
        'data' => base64_encode($binary),
    ];
}

$source = __DIR__.'/../docs/MANUEL_GESTION_MISSIONS.md';
$output = __DIR__.'/../docs/MANUEL_GESTION_MISSIONS.pdf';

if (! is_file($source)) {
    fwrite(STDERR, "Fichier introuvable : {$source}\n");
    exit(1);
}

$markdown = file_get_contents($source);
$htmlBody = Str::markdown($markdown);

$docsDir = dirname($source);
$htmlBody = preg_replace_callback(
    '/<img([^>]*)\ssrc="([^"]+)"/i',
    static function (array $matches) use ($docsDir): string {
        $src = $matches[2];
        if (str_starts_with($src, 'data:') || str_starts_with($src, 'http')) {
            return $matches[0];
        }

        $file = $docsDir.'/'.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $src), DIRECTORY_SEPARATOR);
        if (! is_file($file)) {
            return $matches[0];
        }

        $encoded = encodeImageForPdf($file);
        if ($encoded === null) {
            return $matches[0];
        }

        return '<img'.$matches[1].' src="data:'.$encoded['mime'].';base64,'.$encoded['data'].'"';
    },
    $htmlBody
);

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Manuel — Gestion des missions</title>
<style>
    @page { margin: 2cm 1.8cm; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5px;
        line-height: 1.5;
        color: #1e293b;
    }
    h1 {
        font-size: 19px;
        color: #0f172a;
        border-bottom: 2px solid #2563eb;
        padding-bottom: 6px;
        margin-top: 28px;
        page-break-before: always;
    }
    h1:first-of-type { page-break-before: auto; margin-top: 0; }
    h2 {
        font-size: 15px;
        color: #1e40af;
        margin-top: 22px;
        margin-bottom: 8px;
    }
    h3 {
        font-size: 12px;
        color: #334155;
        margin-top: 14px;
        margin-bottom: 6px;
    }
    h4 { font-size: 11px; color: #475569; margin-top: 10px; }
    p { margin: 6px 0; }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0 14px;
        font-size: 9.5px;
        page-break-inside: avoid;
    }
    th, td {
        border: 1px solid #cbd5e1;
        padding: 5px 7px;
        text-align: left;
        vertical-align: top;
    }
    th { background: #f1f5f9; font-weight: bold; }
    tr:nth-child(even) td { background: #f8fafc; }
    pre {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 10px;
        font-size: 8.5px;
        white-space: pre-wrap;
        word-wrap: break-word;
        page-break-inside: avoid;
    }
    code {
        font-family: DejaVu Sans Mono, monospace;
        font-size: 9px;
        background: #f1f5f9;
        padding: 1px 3px;
    }
    hr {
        border: none;
        border-top: 1px solid #e2e8f0;
        margin: 18px 0;
    }
    ul, ol { margin: 6px 0 10px 18px; padding: 0; }
    li { margin: 3px 0; }
    strong { color: #0f172a; }
    a { color: #2563eb; text-decoration: none; }
    img {
        display: block;
        max-width: 100%;
        height: auto;
        margin: 10px auto 4px;
        page-break-inside: avoid;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
    }
    em {
        display: block;
        font-size: 9px;
        color: #64748b;
        text-align: center;
        margin: 0 0 16px;
        font-style: italic;
    }
    blockquote {
        border-left: 3px solid #93c5fd;
        margin: 10px 0;
        padding: 6px 12px;
        background: #eff6ff;
        color: #1e3a8a;
    }
</style>
</head>
<body>
{$htmlBody}
</body>
</html>
HTML;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

file_put_contents($output, $dompdf->output());

echo "PDF généré : {$output}\n";
echo 'Taille : '.number_format(filesize($output) / 1024, 1).' Ko'."\n";
