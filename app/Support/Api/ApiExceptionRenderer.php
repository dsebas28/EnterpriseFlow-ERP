<?php

namespace App\Support\Api;

use App\Exceptions\BusinessRuleViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps every exception raised under /api to the error envelope, with a
 * stable message per status. Internal details never leave the server
 * outside debug mode; unexpected errors are logged with context.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error('Validation failed.', 422, $e->errors()),
            $e instanceof BusinessRuleViolation => ApiResponse::error($e->getMessage(), 422, ['rule' => [$e->getMessage()]]),
            $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error('This action is not allowed.', 403),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 404),
            $e instanceof ThrottleRequestsException => ApiResponse::error('Too many requests. Please slow down.', 429)
                ->withHeaders($e->getHeaders()),
            $e instanceof HttpExceptionInterface => ApiResponse::error($e->getMessage() ?: 'Request failed.', $e->getStatusCode())
                ->withHeaders($e->getHeaders()),
            default => $this->serverError($e),
        };
    }

    private function serverError(Throwable $e): JsonResponse
    {
        Log::error('api.unhandled_exception', ['exception' => $e::class, 'message' => $e->getMessage()]);

        return ApiResponse::error(
            config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again later.',
            500,
        );
    }
}
