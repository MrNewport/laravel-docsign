<?php

use MrNewport\LaravelDocSign\Services\Pdf\DomPdfRenderer;
use MrNewport\LaravelDocSign\Services\Pdf\WkHtmlToPdfRenderer;

it('renders PDF via DomPdf', function () {
    $renderer = new DomPdfRenderer(['paper'=>'A4','orientation'=>'portrait']);
    $pdf = $renderer->renderPdf('<h1>Hello DomPDF</h1>');
    expect($pdf)->toBeString()->and(strlen($pdf))->toBeGreaterThan(100);
});

it('renders PDF via WkHtmlToPdf', function () {
    // Attempt to locate the binary from env or "which" command
    $pathToBin = env('WKHTMLTOPDF_PATH') ?: trim(shell_exec('which wkhtmltopdf'));

    if (!$pathToBin) {
        $this->markTestSkipped('wkhtmltopdf not installed or not in PATH.');
    }

    $renderer = new WkHtmlToPdfRenderer([
        'paper'=>'A4',
        'orientation'=>'portrait',
        'wkhtml_path' => $pathToBin
    ]);

    $pdf = $renderer->renderPdf('<h1>Hello WkHtml</h1>');
    expect($pdf)->toBeString()->and(strlen($pdf))->toBeGreaterThan(100);
});

