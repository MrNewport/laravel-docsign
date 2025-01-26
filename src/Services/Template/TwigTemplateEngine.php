<?php

namespace MrNewport\LaravelDocSign\Services\Template;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigTemplateEngine implements TemplateEngineInterface
{
    public function render(array $data, string $title = ''): string
    {
        $templateStr = $data['_template_str'] ?? 'Hello {{ name }}';
        $loader = new ArrayLoader(['doc' => $templateStr]);
        $twig = new Environment($loader);

        return $twig->render('doc', $data);
    }
}
