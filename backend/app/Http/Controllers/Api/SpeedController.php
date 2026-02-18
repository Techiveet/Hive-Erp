<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SpeedController extends Controller
{
    protected function success($data, $message = 'Success', $code = 200): JsonResponse
{
    // Fallback to current time if LARAVEL_START is not defined
    $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

    return response()->json([
        'meta' => [
            'latency' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
            'node'    => gethostname(),
        ],
        'status'  => 'success',
        'message' => $message,
        'data'    => $data,
    ], $code);
}

    protected function error($message, $code = 400): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
        ], $code);
    }
}
