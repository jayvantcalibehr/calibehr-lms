<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Inputs never flashed to session on validation exceptions.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register exception handling callbacks.
     *
     * All API routes return {data, code, message} JSON — never HTML error pages.
     * This ensures mobile app and React frontend always get parseable responses.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {

            // Only intercept API/JSON requests
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null; // let Laravel handle web routes normally
            }

            // 401 Unauthenticated — token missing or expired
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'data'    => null,
                    'code'    => 0,
                    'message' => 'Unauthorized. Please login again.',
                ], 401);
            }

            // 422 Validation failed — return first error message
            if ($e instanceof ValidationException) {
                $firstError = collect($e->errors())->flatten()->first();
                return response()->json([
                    'data'    => $e->errors(),
                    'code'    => 0,
                    'message' => $firstError ?? 'Validation failed.',
                ], 422);
            }

            // 404 Model not found (findOrFail etc.)
            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'data'    => null,
                    'code'    => 3,
                    'message' => "{$model} not found.",
                ], 404);
            }

            // 429 Too Many Requests (throttle)
            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'data'    => null,
                    'code'    => 0,
                    'message' => 'Too many attempts. Please wait before trying again.',
                ], 429);
            }

            // 500 Generic server error
            // In production (APP_DEBUG=false) hide internal message for security
            $message = config('app.debug')
                ? $e->getMessage()
                : 'An unexpected error occurred. Please try again.';

            return response()->json([
                'data'    => null,
                'code'    => 0,
                'message' => $message,
            ], 500);
        });
    }
}
