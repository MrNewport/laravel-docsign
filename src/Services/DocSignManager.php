<?php

namespace MrNewport\LaravelDocSign\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use MrNewport\LaravelDocSign\Models\Document;
use MrNewport\LaravelDocSign\Services\Pdf\PdfRendererInterface;
use MrNewport\LaravelDocSign\Services\Signature\SignatureProviderInterface;
use MrNewport\LaravelDocSign\Services\Template\TemplateEngineInterface;

class DocSignManager
{
    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function generate(Document $document): string
    {
        $templateEngine = $this->resolveTemplateEngine();
        $pdfRenderer = $this->resolvePdfRenderer();

        $html = $templateEngine->render($document->data ?? [], $document->title ?? 'Document');
        $pdfBinary = $pdfRenderer->renderPdf($html);

        $filename = ($document->title ?: 'doc') . '_' . uniqid() . '.pdf';
        $disk = config('docsign.storage_disk', 'local');

        Storage::disk($disk)->put($filename, $pdfBinary);
        $path = Storage::disk($disk)->url($filename);

        $document->file_path = $path;
        $document->status = 'draft';
        $document->save();

        return $path;
    }

    public function requestSignature(Document $document, array $signers): array
    {
        $defaultProvider = config('docsign.signature.default', 'local');
        $provider = $this->resolveSignatureProvider($defaultProvider);

        $document->signature_provider = $defaultProvider;
        $document->signers = $signers;
        $document->status = 'signing';
        $document->save();

        return $provider->createSignatureRequest($document, $signers);
    }

    public function handleCallback(string $providerKey, Request $request): void
    {
        $provider = $this->resolveSignatureProvider($providerKey);
        $provider->processCallback($request);
    }

    protected function resolveTemplateEngine(): TemplateEngineInterface
    {
        $engineName = config('docsign.template_engine', 'blade');

        if ($engineName === 'blade') {
            return $this->app->make(\MrNewport\LaravelDocSign\Services\Template\BladeTemplateEngine::class);
        } elseif ($engineName === 'twig') {
            return $this->app->make(\MrNewport\LaravelDocSign\Services\Template\TwigTemplateEngine::class);
        }

        // Fallback container binding => "docsign.template_engine.{engineName}"
        return $this->app->make('docsign.template_engine.' . $engineName);
    }

    protected function resolvePdfRenderer(): PdfRendererInterface
    {
        $renderer = config('docsign.pdf_renderer', 'dompdf');

        if ($renderer === 'dompdf') {
            return $this->app->makeWith(\MrNewport\LaravelDocSign\Services\Pdf\DomPdfRenderer::class, [
                'options' => config('docsign.pdf_options', [])
            ]);
        } elseif ($renderer === 'wkhtml') {
            return $this->app->makeWith(\MrNewport\LaravelDocSign\Services\Pdf\WkHtmlToPdfRenderer::class, [
                'options' => config('docsign.pdf_options', [])
            ]);
        }

        // Fallback container binding => "docsign.pdf_renderer.{renderer}"
        return $this->app->make('docsign.pdf_renderer.' . $renderer);
    }

    protected function resolveSignatureProvider(string $key): SignatureProviderInterface
    {
        $providers = config('docsign.signature.providers', []);
        $info = $providers[$key] ?? null;

        if (!$info) {
            throw new \InvalidArgumentException("Signature provider [{$key}] not defined.");
        }

        return $this->app->make($info['class']);
    }
}
