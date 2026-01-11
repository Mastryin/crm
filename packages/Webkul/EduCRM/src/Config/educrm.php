<?php

return [
    'integrations' => [
        'aisensy' => [
            'api_key' => env('AISENSY_API_KEY'),
            'base_url' => env('AISENSY_BASE_URL', 'https://backend.aisensy.com/campaign/t1/api/v2'),
        ],

        'encharge' => [
            'api_key' => env('ENCHARGE_API_KEY'),
            'base_url' => env('ENCHARGE_BASE_URL', 'https://api.encharge.io/v1'),
        ],

        'trafft' => [
            'api_key' => env('TRAFFT_API_KEY'),
            'base_url' => env('TRAFFT_BASE_URL'),
        ],

        'pabbly' => [
            'webhook_url' => env('PABBLY_WEBHOOK_URL'),
        ],
    ],

    'qualification' => [
        'default_threshold' => 70,
        'auto_assign_qualified' => true,
        'auto_disqualify_negative_keywords' => true,
    ],

    'assignment' => [
        'strategy' => 'round_robin',
        'default_max_load' => 50,
        'default_daily_limit' => 10,
        'consider_specialization' => true,
    ],

    'notifications' => [
        'default_email_provider' => 'smtp',
        'default_whatsapp_provider' => 'aisensy',
        'retry_attempts' => 3,
        'retry_delay_minutes' => 5,
    ],

    'trash' => [
        'retention_days' => 60,
        'cleanup_batch_size' => 100,
    ],

    'deduplication' => [
        'enabled' => true,
        'match_fields' => ['phone', 'email'],
        'merge_priority' => 'application_form',
    ],

    'lead_sources' => [
        'meta_ads' => [
            'name' => 'Meta Ads',
            'auto_qualify' => true,
        ],
        'deftform' => [
            'name' => 'Deftform',
            'auto_qualify' => true,
        ],
        'csv_import' => [
            'name' => 'CSV Import',
            'auto_qualify' => false,
        ],
        'pabbly' => [
            'name' => 'Pabbly Connect',
            'auto_qualify' => true,
        ],
        'manual' => [
            'name' => 'Manual Entry',
            'auto_qualify' => false,
        ],
        'webform' => [
            'name' => 'Website Form',
            'auto_qualify' => true,
        ],
    ],
];
