<?php

namespace MrNewport\LaravelDocSign\Services\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

class DomPdfRenderer implements PdfRendererInterface
{
    protected array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function renderPdf(string $html): string
    {
        $dompdfOptions = new Options();
        $dompdfOptions->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->setPaper(
            $this->options['paper'] ?? 'A4',
            $this->options['orientation'] ?? 'portrait'
        );
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}
