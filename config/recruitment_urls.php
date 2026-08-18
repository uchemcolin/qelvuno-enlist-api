<?php

return [
    'recruitment' => env('RECRUITMENT_URL'),
    'windows_admin' => env('WINDOWS_SERVER_ADMIN_URL'),
    
    // Optional: Add a helper method for Windows URL with port
    'windows_admin_full' => env('WINDOWS_SERVER_ADMIN_URL'),

    // Frontend URL
    'frontend' => env('FRONTEND_URL', 'http://localhost:3000'),

    'enlist' => env('RECRUITMENT_ENLIST_URL')
];