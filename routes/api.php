<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ApplicationDataController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\HealthCheckController;
use App\Http\Controllers\API\LocationController;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PasswordResetController;


// ========== PUBLIC ROUTES ==========

// Auth
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,5');
Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:3,60');
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

// Helper endpoints (public)
Route::get('/states', [LocationController::class, 'getStates']);
Route::get('/lgas/{stateCode}', [LocationController::class, 'getLocalGovernments']);
Route::get('/health', [HealthCheckController::class, 'check']);
Route::get('/health/detailed', [HealthCheckController::class, 'detailed']);

// ========== PROTECTED ROUTES ==========
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Application submission routes (5 steps total)
    Route::prefix('application')->group(function () {
        // STEP 1: Personal Information
        Route::post('/personal-info', [ApplicationController::class, 'submitPersonalInfo']);
        
        // STEP 2: Applicant Address (permanent + residential)
        Route::post('/addresses', [ApplicationController::class, 'submitAddresses']);
        
        // STEP 3: Next of Kin
        Route::post('/next-of-kin', [ApplicationController::class, 'submitNextOfKin']);
        
        // STEP 4: Education AND Work Experience (combined)
        Route::post('/education-work-experience', [ApplicationController::class, 'submitEducationAndWorkExperience']);
        
        // STEP 5: NYSC and Professional Qualification
        Route::post('/nysc-professional', [ApplicationController::class, 'submitNyscProfessional']);
        
        // Final submission (locks the application)
        Route::post('/complete', [ApplicationController::class, 'completeApplication']);

        // Single submission route (replaces all steps)
        Route::post('/submit-full', [ApplicationController::class, 'submitFullApplication']);

        // Data retrieval for printable page
        Route::get('/full', [ApplicationDataController::class, 'getFullApplication']);
        Route::get('/reference/{referenceNo}', [ApplicationDataController::class, 'getApplicationByReference']);

        // Get current progress (5-step version)
        Route::get('/progress', [ApplicationController::class, 'getApplicationProgress']);
    });
});