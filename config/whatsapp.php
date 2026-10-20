<?php

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'api_version' => env('WHATSAPP_API_VERSION'),
    // Public WhatsApp number, distinct from Meta's internal phone number ID.
    'phone_number' => env('WHATSAPP_PHONE_NUMBER'),
    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'id'),
    'templates' => [
        'three_ahead' => env('WHATSAPP_TEMPLATE_THREE_AHEAD'),
        'one_ahead' => env('WHATSAPP_TEMPLATE_ONE_AHEAD'),
        'called' => env('WHATSAPP_TEMPLATE_CALLED'),
        'cancelled' => env('WHATSAPP_TEMPLATE_CANCELLED'),
    ],
];
