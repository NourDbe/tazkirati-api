
<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Validation errors: 422
        $exceptions->render(function (
            ValidationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'بيانات الطلب غير صالحة.',
                'errors' => $exception->errors(),
            ], 422);
        });

        // Authentication errors: 401
        $exceptions->render(function (
            AuthenticationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً.',
                'errors' => (object) [],
            ], 401);
        });

        // Authorization errors: 403
        $exceptions->render(function (
            AuthorizationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
                'errors' => (object) [],
            ], 403);
        });

        // Model not found: 404
        $exceptions->render(function (
            ModelNotFoundException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'العنصر المطلوب غير موجود.',
                'errors' => (object) [],
            ], 404);
        });

        // Other HTTP errors: 404, 405, etc.
        $exceptions->render(function (
            HttpExceptionInterface $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();

            $message = match ($status) {
                404 => 'المسار أو العنصر المطلوب غير موجود.',
                405 => 'طريقة الطلب غير مسموح بها.',
                default => 'تعذر تنفيذ الطلب.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => (object) [],
            ], $status);
        });

        // Unexpected errors: 500
        $exceptions->render(function (
            \Throwable $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ داخلي في الخادم.',
                'errors' => (object) [],
            ], 500);
        });
    })->create();
