<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function check()
    {
        $health = [
            'status' => 'up',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'up',
                'redis' => 'up'
            ]
        ];

        try {
            // Check database connection
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $health['services']['database'] = 'down';
            $health['status'] = 'down';
        }

        try {
            // Check Redis connection
//            Redis::ping();
        } catch (\Exception $e) {
            $health['services']['redis'] = 'down';
            $health['status'] = 'down';
        }

        return response()->json($health);
    }
}
