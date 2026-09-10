<?php

return [
    'documentations' => [
        'default' => [
            'api' => [
                /*
                |--------------------------------------------------------------------------
                | API Info
                |--------------------------------------------------------------------------
                */
                'title' => 'Sistema Académico API',
                'description' => 'API REST del Sistema de Gestión Académica - Endpoints para administración, profesores y estudiantes',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Desarrollo',
                    'email' => 'admin@academico.local',
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT',
                ],
            ],

            'routes' => [
                'api' => 'api/documentation',
                'docs' => 'api/docs',
                'middleware' => [
                    'api' => [],
                    'docs' => [],
                    'asset' => [],
                ],
            ],

            'paths' => [
                'docs' => storage_path('api-docs'),
                'annotations' => base_path('app'),
                'output' => storage_path('api-docs/api-docs.json'),
            ],

            'scan' => [
                'include' => [
                    base_path('app/Http/Controllers'),
                    base_path('app/Http/Requests'),
                    base_path('app/Http/Resources'),
                    base_path('app/Models'),
                ],
                'exclude' => [
                    base_path('app/Http/Controllers/Controller.php'),
                ],
            ],

            'security' => [
                'definitions' => [
                    'SecurityScheme' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
        ],
    ],

    'defaults' => [
        'routes' => [
            'api' => 'api/documentation',
            'docs' => 'api/docs',
        ],
        'paths' => [
            'docs' => storage_path('api-docs'),
            'annotations' => base_path('app'),
            'output' => storage_path('api-docs/api-docs.json'),
        ],
        'scan' => [
            'include' => [
                base_path('app/Http/Controllers'),
                base_path('app/Http/Requests'),
                base_path('app/Http/Resources'),
                base_path('app/Models'),
            ],
            'exclude' => [
                base_path('app/Http/Controllers/Controller.php'),
            ],
        ],
    ],

    'ui' => [
        'display' => [
            'page_title' => 'Sistema Académico - Documentación API',
            'show_extensions' => true,
            'deep_linking' => true,
            'try_it_out_enabled' => true,
        ],
        'sort_endpoints' => 'path',
    ],
];