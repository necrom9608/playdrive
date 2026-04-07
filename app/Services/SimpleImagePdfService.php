<?php

namespace App\Services;

use RuntimeException;

class SimpleImagePdfService
{
    public function fromPngString(string $pngBinary, float $pageWidthMm, float $pageHeightMm): string
    {
        $image = @imagecreatefromstring($pngBinary);
        if (! $image) {
            throw new RuntimeException('PNG kon niet gelezen worden voor PDF-opbouw.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $flattened = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefilledrectangle($flattened, 0, 0, $width, $height, $white);
        imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($flattened, null, 95);
        $jpegBinary = (string) ob_get_clean();
        imagedestroy($flattened);
        imagedestroy($image);

        if ($jpegBinary === '') {
            throw new RuntimeException('JPEG-conversie voor PDF is mislukt.');
        }

        return $this->buildPdfFromJpeg($jpegBinary, $width, $height, $pageWidthMm, $pageHeightMm);
    }

    private function buildPdfFromJpeg(string $jpegBinary, int $imageWidth, int $imageHeight, float $pageWidthMm, float $pageHeightMm): string
    {
        $pageWidth = $this->mmToPoints($pageWidthMm);
        $pageHeight = $this->mmToPoints($pageHeightMm);
        $imageLength = strlen($jpegBinary);
        $contentStream = sprintf("q\n%.6F 0 0 %.6F 0 0 cm\n/Im0 Do\nQ\n", $pageWidth, $pageHeight);

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Count 1 /Kids [3 0 R] >>";
        $objects[] = sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.6F %.6F] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>",
            $pageWidth,
            $pageHeight,
        );
        $objects[] = sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            $imageWidth,
            $imageHeight,
            $imageLength,
            $jpegBinary,
        );
        $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($contentStream), $contentStream);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function mmToPoints(float $millimetres): float
    {
        return ($millimetres / 25.4) * 72;
    }
}
