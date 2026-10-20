<?php

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    // "meta" (WhatsApp Cloud API) or "twilio" (Twilio WhatsApp API).
    'provider' => env('WHATSAPP_PROVIDER', 'meta'),
    // Public WhatsApp number customers chat with. With Twilio this is also the sender ("From") number.
    'phone_number' => env('WHATSAPP_PHONE_NUMBER'),

    // Meta Cloud API
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'api_version' => env('WHATSAPP_API_VERSION'),

    // Twilio
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
    ],

    // Meta: approved template names. Twilio: approved Content SIDs (HX...).
    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'id'),
    'templates' => [
        'three_ahead' => env('WHATSAPP_TEMPLATE_THREE_AHEAD'),
        'one_ahead' => env('WHATSAPP_TEMPLATE_ONE_AHEAD'),
        'called' => env('WHATSAPP_TEMPLATE_CALLED'),
        'cancelled' => env('WHATSAPP_TEMPLATE_CANCELLED'),
    ],
];
