<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This file controls which frontend applications (origins) are allowed
    | to access your Laravel API from a browser.
    |
    | IMPORTANT:
    | Postman ignores CORS, but browsers enforce it strictly.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Paths that should use CORS middleware
    |--------------------------------------------------------------------------
    | Usually all API routes and Sanctum CSRF endpoint.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP methods
    |--------------------------------------------------------------------------
    | '*' allows GET, POST, PUT, PATCH, DELETE, OPTIONS, etc.
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins (VERY IMPORTANT IN PRODUCTION)
    |--------------------------------------------------------------------------
    | These are the ONLY domains allowed to call your API from a browser.
    |
    | DO NOT use '*' in production if you use cookies/authentication.
    |
    | Add all frontend environments here:
    */
    'allowed_origins' => [
        'http://localhost:3000',        // local React dev (Mac/Windows)
        'http://127.0.0.1:3000',        // alternative local origin

        'http://localhost:3004',        // alternative local origin for test on [IP]
        'http://[IP]:3004',       // alternative local origin for test on [IP]
        // 👉 Add production frontend domain(s) here:
        // 'https://your-frontend.com',
        // 'https://app.your-frontend.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origin Patterns
    |--------------------------------------------------------------------------
    | Used for dynamic subdomains (rare use case).
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    | '*' allows all headers like Authorization, Content-Type, etc.
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    | Headers that the browser is allowed to read from JS.
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Preflight Cache Time
    |--------------------------------------------------------------------------
    | How long browser caches OPTIONS preflight response (in seconds).
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Credentials Support (IMPORTANT)
    |--------------------------------------------------------------------------
    | Set to TRUE if:
    | - You use Sanctum
    | - You use cookies/session authentication
    | - You send withCredentials from Axios/fetch
    |
    | WARNING:
    | If TRUE, you MUST NOT use '*' in allowed_origins.
    */
    'supports_credentials' => false,

];