<?php
declare(strict_types=1);

namespace App\Core;

final class Pdf
{
    public static function download(array $lines, string $filename): void
    {
        $content = self::buildContentStream($lines);
        $pdf = self::buildPdfDocument($content);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function buildContentStream(array $lines): string
    {
        $escapedLines = [];
        foreach ($lines as $line) {
            $text = self::escape((string)$line);
            $escapedLines[] = '(' . $text . ') Tj';
        }

        if (empty($escapedLines)) {
            $escapedLines[] = '(TacoMap France) Tj';
        }

        return "BT\n/F1 12 Tf\n50 790 Td\n16 TL\n" . implode("\nT*\n", $escapedLines) . "\nET";
    }

    private static function buildPdfDocument(string $contentStream): string
    {
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objNum = $index + 1;
            $pdf .= $objNum . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 " . $count . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }

        $pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private static function escape(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = str_replace("\n", ' ', $value);
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $value
        );
    }
}
