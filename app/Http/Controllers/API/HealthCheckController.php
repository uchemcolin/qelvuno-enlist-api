<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Simple health check endpoint
     */
    public function check()
    {
        $status = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            $status['database'] = 'connected';
        } catch (\Exception $e) {
            $status['database'] = 'error: ' . $e->getMessage();
            $status['status'] = 'unhealthy';
        }
        
        // Check Redis connection (if configured)
        if (env('REDIS_HOST')) {
            try {
                Redis::ping();
                $status['redis'] = 'connected';
            } catch (\Exception $e) {
                $status['redis'] = 'error: ' . $e->getMessage();
                $status['status'] = 'degraded';
            }
        }
        
        // Check queue connection
        try {
            $status['queue'] = env('QUEUE_CONNECTION', 'database');
        } catch (\Exception $e) {
            $status['queue'] = 'error';
        }
        
        return response()->json($status, $status['status'] === 'healthy' ? 200 : 500);
    }
    
    /**
     * Detailed health check (for admin/support)
     */
    public function detailed()
    {
        $status = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'checks' => []
        ];
        
        // Database check
        try {
            DB::connection()->getPdo();
            $status['checks']['database'] = [
                'status' => 'ok',
                'message' => 'Connected successfully'
            ];
        } catch (\Exception $e) {
            $status['checks']['database'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $status['status'] = 'unhealthy';
        }
        
        // Redis check
        if (env('REDIS_HOST')) {
            try {
                Redis::ping();
                $status['checks']['redis'] = [
                    'status' => 'ok',
                    'message' => 'Connected successfully'
                ];
            } catch (\Exception $e) {
                $status['checks']['redis'] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $status['status'] = 'degraded';
            }
        }
        
        // Mail check
        $status['checks']['mail'] = [
            'status' => 'ok',
            'message' => 'Mail driver: ' . env('MAIL_MAILER', 'log'),
            'from_address' => env('MAIL_FROM_ADDRESS', 'not set')
        ];
        
        // Storage check
        try {
            $storagePath = storage_path('app/public');
            if (is_writable($storagePath)) {
                $status['checks']['storage'] = [
                    'status' => 'ok',
                    'message' => 'Writable'
                ];
            } else {
                $status['checks']['storage'] = [
                    'status' => 'warning',
                    'message' => 'Storage not writable'
                ];
            }
        } catch (\Exception $e) {
            $status['checks']['storage'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Queue check
        $status['checks']['queue'] = [
            'status' => 'ok',
            'message' => 'Driver: ' . env('QUEUE_CONNECTION', 'database')
        ];
        
        // Statistics (optional)
        try {
            $status['stats'] = [
                'users' => DB::table('users')->count(),
                'applications' => DB::table('personal_info')->count(),
                'completed_applications' => DB::table('personal_info')->whereNotNull('referenceNo')->count(),
            ];
        } catch (\Exception $e) {
            // Ignore stats errors
        }
        
        $responseCode = $status['status'] === 'healthy' ? 200 : 
                        ($status['status'] === 'degraded' ? 200 : 500);
        
        return response()->json($status, $responseCode);
    }
}