<?php

use MrNewport\LaravelDocSign\Services\Template\BladeTemplateEngine;
use MrNewport\LaravelDocSign\Services\Template\TwigTemplateEngine;

it('renders blade template', function () {
    $engine = new BladeTemplateEngine();
    $html = $engine->render(['_template'=>'docsign::test','name'=>'John']);
    expect($html)->toContain('John');
});

it('renders twig template', function () {
    $engine = new TwigTemplateEngine();
    $html = $engine->render(['_template_str'=>'Hello {{ name }}','name'=>'Jane']);
    expect($html)->toBe('Hello Jane');
});
