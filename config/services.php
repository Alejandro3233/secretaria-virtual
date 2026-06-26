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

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
        'twiml_app_sid' => env('TWILIO_TWIML_APP_SID'),
        'browser_ring_timeout' => (int) env('TWILIO_BROWSER_RING_TIMEOUT', 18),
        'from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'auto_buy_numbers' => env('TWILIO_AUTO_BUY_NUMBERS', false),
        'voice_webhook_url' => env('TWILIO_VOICE_WEBHOOK_URL', env('APP_URL').'/twilio/voice/incoming'),
        'number_types' => array_filter(array_map('trim', explode(',', env('TWILIO_NUMBER_TYPES', 'local,mobile')))),
        'supported_countries' => array_filter(array_map('trim', explode(',', env('TWILIO_SUPPORTED_COUNTRIES', 'US,CA,GB,ES,MX,CO')))),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'usage_costs' => [
        'sms_usd' => (float) env('SV_COST_SMS_USD', 0.01),
        'call_usd' => (float) env('SV_COST_CALL_USD', 0.03),
        'email_usd' => (float) env('SV_COST_EMAIL_USD', 0.001),
        'machine_monthly_usd' => (float) env('SV_COST_MACHINE_MONTHLY_USD', 0),
    ],

];
