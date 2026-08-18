<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Support Contact Information
    |--------------------------------------------------------------------------
    |
    | These values are used in email templates to provide users with 
    | support contact information.
    |
    */
    
    'email' => env('SUPPORT_EMAIL', 'support@qelvuno.gov.ng'),
    'phone' => env('SUPPORT_PHONE', '+234 800 123 4567'),
    'phone_display' => env('SUPPORT_PHONE_DISPLAY', '0800 123 4567'),
    'hours' => env('SUPPORT_HOURS', 'Monday - Friday, 8:00 AM - 5:00 PM WAT'),
];