<?php

namespace MrNewport\LaravelDocSign\Services\Pdf;

interface PdfRendererInterface
{
    public function renderPdf(string $html): string;
}
