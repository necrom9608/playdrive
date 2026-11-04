<?php

namespace App\Support\Pdf;

use RuntimeException;

/**
 * Minimale PDF-writer zonder externe library: A4-pagina's met Helvetica (WinAnsi),
 * tekst, lijnen, rechthoeken, polylijnen (handtekening) en JPEG-afbeeldingen.
 *
 * Coördinaten zijn in punten (1/72 inch) vanaf linksboven; bij tekst is y de basislijn.
 */
class SimplePdf
{
    public const A4_WIDTH = 595.28;
    public const A4_HEIGHT = 841.89;

    /** @var string[] content stream per pagina */
    private array $pages = [];

    private int $current = -1;

    /** @var array<int, array{data: string, w: int, h: int}> */
    private array $images = [];

    private string $title = '';

    public function __construct(
        private readonly float $width = self::A4_WIDTH,
        private readonly float $height = self::A4_HEIGHT,
    ) {
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function addPage(): int
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;

        return $this->current;
    }

    public function setPage(int $index): void
    {
        if (! isset($this->pages[$index])) {
            throw new RuntimeException('Pagina bestaat niet.');
        }
        $this->current = $index;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function pageWidth(): float
    {
        return $this->width;
    }

    public function pageHeight(): float
    {
        return $this->height;
    }

    // ------------------------------------------------------------------
    // Tekst
    // ------------------------------------------------------------------

    /**
     * @param  int[]  $color  RGB 0-255
     * @param  string  $align  left | right | center (x is het ankerpunt)
     */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $color = [15, 23, 42], string $align = 'left'): void
    {
        $encoded = $this->encode($text);
        if ($encoded === '') {
            return;
        }

        $width = $this->encodedWidth($encoded, $size, $bold);
        if ($align === 'right') {
            $x -= $width;
        } elseif ($align === 'center') {
            $x -= $width / 2;
        }

        $this->out(sprintf(
            "BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $this->rgb($color),
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $this->height - $y,
            $this->escape($encoded),
        ));
    }

    public function textWidth(string $text, float $size = 10, bool $bold = false): float
    {
        return $this->encodedWidth($this->encode($text), $size, $bold);
    }

    /**
     * Breek tekst af op woorden zodat elke regel binnen $maxWidth past. Respecteert nieuwe lijnen.
     *
     * @return string[]
     */
    public function wrap(string $text, float $maxWidth, float $size = 10, bool $bold = false): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph)) ?: [];
            $line = '';

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $candidate = $line === '' ? $word : $line . ' ' . $word;

                if ($this->textWidth($candidate, $size, $bold) <= $maxWidth) {
                    $line = $candidate;
                    continue;
                }

                if ($line !== '') {
                    $lines[] = $line;
                }

                // Eén woord langer dan de regel: hard afbreken.
                while ($this->textWidth($word, $size, $bold) > $maxWidth && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word);
                    while ($cut > 1 && $this->textWidth(mb_substr($word, 0, $cut), $size, $bold) > $maxWidth) {
                        $cut--;
                    }
                    $lines[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $line = $word;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    // ------------------------------------------------------------------
    // Vormen
    // ------------------------------------------------------------------

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5, array $color = [203, 213, 225]): void
    {
        $this->out(sprintf(
            "q %s RG %.2F w %.2F %.2F m %.2F %.2F l S Q\n",
            $this->rgb($color),
            $width,
            $x1,
            $this->height - $y1,
            $x2,
            $this->height - $y2,
        ));
    }

    /**
     * Rechthoek met optionele vulling en/of rand. y = bovenkant.
     */
    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lineWidth = 0.5): void
    {
        if (! $fill && ! $stroke) {
            return;
        }

        $ops = 'q ';
        if ($fill) {
            $ops .= $this->rgb($fill) . ' rg ';
        }
        if ($stroke) {
            $ops .= $this->rgb($stroke) . ' RG ' . sprintf('%.2F w ', $lineWidth);
        }
        $ops .= sprintf('%.2F %.2F %.2F %.2F re ', $x, $this->height - $y - $h, $w, $h);
        $ops .= ($fill && $stroke) ? 'B' : ($fill ? 'f' : 'S');
        $this->out($ops . " Q\n");
    }

    /**
     * Polylijn met ronde uiteinden (bv. een handtekeningstreek). Punten als [[x, y], ...].
     */
    public function polyline(array $points, float $width = 1.5, array $color = [15, 23, 42]): void
    {
        $points = array_values($points);
        if ($points === []) {
            return;
        }

        $ops = sprintf('q %s RG %.2F w 1 J 1 j ', $this->rgb($color), $width);
        [$x0, $y0] = $points[0];
        $ops .= sprintf('%.2F %.2F m ', $x0, $this->height - $y0);

        if (count($points) === 1) {
            // Een stip: kort lijntje zodat de ronde dop zichtbaar wordt.
            $ops .= sprintf('%.2F %.2F l ', $x0 + 0.1, $this->height - $y0);
        }

        for ($i = 1, $n = count($points); $i < $n; $i++) {
            [$x, $y] = $points[$i];
            $ops .= sprintf('%.2F %.2F l ', $x, $this->height - $y);
        }

        $this->out($ops . "S Q\n");
    }

    // ------------------------------------------------------------------
    // Afbeeldingen
    // ------------------------------------------------------------------

    /**
     * Voeg een JPEG toe (binaire data). y = bovenkant van de afbeelding.
     */
    public function jpeg(string $jpegBinary, int $pixelWidth, int $pixelHeight, float $x, float $y, float $w, float $h): void
    {
        $this->images[] = ['data' => $jpegBinary, 'w' => $pixelWidth, 'h' => $pixelHeight];
        $name = 'Im' . (count($this->images) - 1);

        $this->out(sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $w,
            $h,
            $x,
            $this->height - $y - $h,
            $name,
        ));
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    public function output(): string
    {
        if ($this->pages === []) {
            $this->addPage();
        }

        $objects = [];
        $pageCount = count($this->pages);
        $imageCount = count($this->images);

        // Vaste nummering: 1 catalog, 2 pages, 3-4 fonts, 5 info, dan afbeeldingen, dan per pagina (page + content).
        $firstImage = 6;
        $firstPage = $firstImage + $imageCount;

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPage + $i * 2) . ' 0 R';
        }

        $xobjects = '';
        for ($i = 0; $i < $imageCount; $i++) {
            $xobjects .= '/Im' . $i . ' ' . ($firstImage + $i) . ' 0 R ';
        }
        $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>' . ($xobjects !== '' ? ' /XObject << ' . $xobjects . '>>' : '') . ' >>';

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Count ' . $pageCount . ' /Kids [' . implode(' ', $kids) . '] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Producer (Playdrive) /Title (' . $this->escape($this->encode($this->title)) . ') /CreationDate (D:' . gmdate('YmdHis') . 'Z) >>';

        foreach ($this->images as $i => $image) {
            $objects[$firstImage + $i] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $image['w'],
                $image['h'],
                strlen($image['data']),
                $image['data'],
            );
        }

        foreach ($this->pages as $i => $content) {
            $pageObj = $firstPage + $i * 2;
            $contentObj = $pageObj + 1;

            $objects[$pageObj] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>',
                $this->width,
                $this->height,
                $resources,
                $contentObj,
            );

            if (function_exists('gzcompress')) {
                $stream = gzcompress($content, 6);
                $objects[$contentObj] = '<< /Length ' . strlen($stream) . " /Filter /FlateDecode >>\nstream\n" . $stream . "\nendstream";
            } else {
                $objects[$contentObj] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            }
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $size = count($objects) + 1;
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    // ------------------------------------------------------------------
    // Intern
    // ------------------------------------------------------------------

    private function out(string $ops): void
    {
        if ($this->current < 0) {
            $this->addPage();
        }
        $this->pages[$this->current] .= $ops;
    }

    private function rgb(array $color): string
    {
        [$r, $g, $b] = array_pad(array_values($color), 3, 0);

        return sprintf('%.3F %.3F %.3F', $r / 255, $g / 255, $b / 255);
    }

    /** UTF-8 → Windows-1252 (WinAnsi); tekens die niet bestaan worden '?'. */
    private function encode(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return is_string($converted) ? $converted : '';
    }

    private function escape(string $encoded): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function encodedWidth(string $encoded, float $size, bool $bold): float
    {
        $widths = $bold ? self::WIDTHS_BOLD : self::WIDTHS_REGULAR;
        $total = 0;

        for ($i = 0, $n = strlen($encoded); $i < $n; $i++) {
            $code = ord($encoded[$i]);
            $total += $code >= 32 ? ($widths[$code - 32] ?? 556) : 0;
        }

        return $total * $size / 1000;
    }

    /** Breedtes (1/1000 em) voor WinAnsi-codes 32..255. */
    private const WIDTHS_REGULAR = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 761,
        556, 278, 222, 556, 333, 1000, 556, 556, 333, 1000, 667, 333, 1000, 278, 611, 278,
        278, 222, 222, 333, 333, 350, 556, 1000, 333, 1000, 500, 333, 944, 278, 500, 667,
        278, 333, 556, 556, 556, 556, 260, 556, 333, 737, 370, 556, 584, 333, 737, 333,
        400, 584, 333, 333, 333, 556, 537, 278, 333, 333, 365, 556, 834, 834, 834, 611,
        667, 667, 667, 667, 667, 667, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
        722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
        556, 556, 556, 556, 556, 556, 889, 500, 556, 556, 556, 556, 278, 278, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 584, 611, 556, 556, 556, 556, 500, 556, 500,
    ];

    /** Breedtes (1/1000 em) voor WinAnsi-codes 32..255. */
    private const WIDTHS_BOLD = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 761,
        556, 278, 278, 556, 500, 1000, 556, 556, 333, 1000, 667, 333, 1000, 278, 611, 278,
        278, 278, 278, 500, 500, 350, 556, 1000, 333, 1000, 556, 333, 944, 278, 500, 667,
        278, 333, 556, 556, 556, 556, 280, 556, 333, 737, 370, 556, 584, 333, 737, 333,
        400, 584, 333, 333, 333, 611, 556, 278, 333, 333, 365, 556, 834, 834, 834, 611,
        722, 722, 722, 722, 722, 722, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
        722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
        556, 556, 556, 556, 556, 556, 889, 556, 556, 556, 556, 556, 278, 278, 278, 278,
        611, 611, 611, 611, 611, 611, 611, 584, 611, 611, 611, 611, 611, 556, 611, 556,
    ];
}
