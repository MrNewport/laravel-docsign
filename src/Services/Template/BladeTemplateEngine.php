<?php

namespace MrNewport\LaravelDocSign\Services\Template;

use Illuminate\Support\Facades\View;

class BladeTemplateEngine implements TemplateEngineInterface
{
    public function render(array $data, string $title = ''): string
    {
        $viewName = $data['_template'] ?? 'docsign::default';
        return View::make($viewName, $data)->render();
    }
}
