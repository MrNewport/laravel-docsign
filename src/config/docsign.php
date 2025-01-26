<?php

return [

    'pdf_renderer' => 'dompdf',
    'pdf_options' => [
        'paper' => 'A4',
        'orientation' => 'portrait'
    ],

    'template_engine' => 'blade',

    'signature' => [
        'default' => 'local',
        'providers' => [
            'local' => [
                'class' => \MrNewport\LaravelDocSign\Services\Signature\LocalSignatureProvider::class,
                'callback_url' => '/docsign/local/callback'
            ],
            'docusign' => [
                'class' => \MrNewport\LaravelDocSign\Services\Signature\DocuSignSignatureProvider::class,
                'api_key' => env('DOCUSIGN_API_KEY',''),
                'callback_url' => '/docsign/docusign/callback'
            ],
            'hellosign' => [
                'class' => \MrNewport\LaravelDocSign\Services\Signature\HelloSignSignatureProvider::class,
                'api_key' => env('HELLOSIGN_API_KEY',''),
                'callback_url' => '/docsign/hellosign/callback'
            ]
        ],
    ],

    'storage_disk' => 'local',
];
