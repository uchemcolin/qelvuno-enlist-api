<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview-email', function () {
    return new ApplicationConfirmationMail(
        'John Doe',
        'FIRS-IA-1125ABC12345',
        'john@example.com',
        '08012345678',
        'http://localhost:3000/login'
    );
});
