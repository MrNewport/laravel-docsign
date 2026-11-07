<?php

return [

    'callbacks' => ['enabled' => false, 'middleware' => []],

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
                'enabled' => false,
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

    // Separate from the legacy Document demo API; both modes remain disabled until explicitly enabled.
    'private_docusign' => [
        'enabled' => env('DOCSIGN_PRIVATE_DOCUSIGN_ENABLED', false),
        'environment' => env('DOCSIGN_PRIVATE_DOCUSIGN_ENVIRONMENT', 'demo'),
        'production_enabled' => (bool) env('DOCSIGN_PRIVATE_DOCUSIGN_PRODUCTION_ENABLED', false),
        'integration_key' => env('DOCUSIGN_INTEGRATION_KEY', ''),
        'user_id' => env('DOCUSIGN_IMPERSONATED_USER_ID', ''),
        'account_id' => env('DOCUSIGN_ACCOUNT_ID', ''),
        'rsa_private_key_path' => env('DOCUSIGN_RSA_PRIVATE_KEY_PATH', ''),
        'rsa_private_key_pem' => '', // Bind a decoded in-memory key at runtime; never cache it here.
        'hmac_keys' => array_filter([env('DOCUSIGN_CONNECT_HMAC_KEY', '')]),
        'connect_configuration_id' => env('DOCUSIGN_CONNECT_CONFIGURATION_ID', ''),
    ],
];
