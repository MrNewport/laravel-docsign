# DocSign feature index

| Public surface → return | Behavior / failure | Guide |
| --- | --- | --- |
| `DocSignManager::__construct(Container)`, `generate(Document): string` | Trusted HTML→PDF→UUID object→disk URL and draft save; renderer errors propagate; failed put → RuntimeException. | [Generate](HANDBOOK.md#generate-a-first-document), [recovery](HANDBOOK.md#documents-storage-and-failure-recovery) |
| `requestSignature(Document, array $signers): array` | Provider call before signing status; undefined provider → InvalidArgumentException; provider errors propagate. No universal signer schema/idempotency. | [Signatures](HANDBOOK.md#signature-requests-and-callbacks) |
| `handleCallback(string $providerKey, Request): void` | Delegates callback authentication/status policy to adapter. | [Callbacks](HANDBOOK.md#signature-requests-and-callbacks) |
| `Facades\DocSign` / docsign.manager | Facade for three manager methods; root empty DocSign class is not API. | [Install](HANDBOOK.md#fresh-laravel-installation) |
| `TemplateEngineInterface::render(array, string $title = ''): string` | Template extension contract. | [Templates](HANDBOOK.md#template-engines) |
| `BladeTemplateEngine::render(...)` | _template/default; explicit title wins; application view escaping. | [Templates](HANDBOOK.md#template-engines) |
| `TwigTemplateEngine::render(...)` | _template_str/default; supplied data only; explicit escaping required for untrusted values. | [Templates](HANDBOOK.md#template-engines) |
| `PdfRendererInterface::renderPdf(string $html): string` | Byte renderer contract; no storage required. | [PDF](HANDBOOK.md#pdf-engines) |
| `DomPdfRenderer::__construct(array = [])`, `renderPdf(string): string` | paper/orientation/remote_enabled; embedded PHP off; remote false default. | [PDF](HANDBOOK.md#pdf-engines) |
| `WkHtmlToPdfRenderer::__construct(array = [])`, `renderPdf(string): string` | wkhtml_path/env/PATH; external binary required; local access and JS disabled. | [PDF](HANDBOOK.md#pdf-engines) |
| `SignatureProviderInterface::createSignatureRequest(Document, array): array`, `processCallback(Request): void` | Adapter owns provider authentication/replay/request association. | [Signatures](HANDBOOK.md#signature-requests-and-callbacks), [recipe](RECIPES.md#external-provider-implementation-checklist-expressed-as-contracts) |
| LocalSignatureProvider | Explicit dual opt-in;15 minute signed POST;403 signature / 404 missing / 409 state; demonstration only. | [Local tutorial](HANDBOOK.md#local-demonstration) |
| DocuSignSignatureProvider / HelloSignSignatureProvider | Both operations throw LogicException; no live external integration. | [Signatures](HANDBOOK.md#signature-requests-and-callbacks) |
| `RouteCallbacks::signatureCallback($provider, Request)` → JsonResponse | POST docsign.callback only when enabled; configured middleware; adapter authenticates. | [Callbacks](HANDBOOK.md#signature-requests-and-callbacks) |
| Document model / documents migration | Title/path/status/data/provider/signers, array casts, no ownership. | [Data](HANDBOOK.md#documents-storage-and-failure-recovery) |
| DocSignServiceProvider / docsign-config / views | Discovery, migrations, conditional routes, named custom engine/renderer bindings. | [Install](HANDBOOK.md#fresh-laravel-installation), [config](HANDBOOK.md#configuration-upgrades-and-verification) |
| Optional Fields PDF composition | Redacted report through renderer, no Document/signing state. | [Recipe](RECIPES.md#fields-report-without-documentsigning-state) |

Executable source examples: [PDF tests](../tests/Feature/PdfTest.php), [template tests](../tests/Feature/TemplateTest.php), [document tests](../tests/Feature/DocumentTest.php), [signature tests](../tests/Feature/SignatureTest.php). Run composer test; review conditional wkhtml/MySQL skips explicitly.
