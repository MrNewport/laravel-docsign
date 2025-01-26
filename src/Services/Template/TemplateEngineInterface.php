<?php

namespace MrNewport\LaravelDocSign\Services\Template;

interface TemplateEngineInterface
{
    public function render(array $data, string $title = ''): string;
}
