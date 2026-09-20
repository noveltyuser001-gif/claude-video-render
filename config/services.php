<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lovable / Supabase video-template sources
    |--------------------------------------------------------------------------
    |
    | Each entry is an NHS Portal Lovable project whose `video_templates`
    | table can be pulled in via `php artisan lovable:import-templates
    | {source}`. Keys are the "source" name recorded on the imported
    | VideoTemplate rows. Set the URL/key in .env, not here.
    |
    */

    'lovable' => [
        'nhs-portal-1' => [
            'url' => env('LOVABLE_NHS_PORTAL_1_URL'),
            'key' => env('LOVABLE_NHS_PORTAL_1_KEY'),
            'bucket' => 'video-templates',
        ],
    ],

];
