<?php

namespace MrNewport\LaravelDocSign\Services\Pdf;

use Knp\Snappy\Pdf;

class WkHtmlToPdfRenderer implements PdfRendererInterface
{
    protected Pdf $snappy;
    protected array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;

        // Fetch path from options or fallback to "wkhtmltopdf" in PATH
        $wkhtmlPath = $options['wkhtml_path']
            ?? env('WKHTMLTOPDF_PATH')
            ?? 'wkhtmltopdf'; // fallback if not set

        $this->snappy = new Pdf($wkhtmlPath);

        $this->snappy->setOption('enable-local-file-access', true);

        if (!empty($options['paper'])) {
            $this->snappy->setOption('page-size', $options['paper']);
        }

        if (!empty($options['orientation'])) {
            $this->snappy->setOption('orientation', ucfirst($options['orientation']));
        }
    }

    public function renderPdf(string $html): string
    {
        return $this->snappy->getOutputFromHtml($html);
    }
}
