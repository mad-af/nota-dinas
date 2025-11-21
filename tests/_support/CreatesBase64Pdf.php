<?php

namespace Tests\_support;

trait CreatesBase64Pdf
{
    protected function makeBase64Pdf(string $content = 'Minimal PDF'): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        return base64_encode($pdf);
    }
}