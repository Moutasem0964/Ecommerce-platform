<?php

namespace App\Exceptions;

use App\Services\Inventory\Exceptions\InsufficientStockException;
use App\Services\Inventory\Exceptions\InventoryUnavailableException;
use App\Services\Inventory\Exceptions\ReservationConflictException;
use App\Services\Orders\Exceptions\InvalidStatusTransitionException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception thrown during an API request into the same JSON shape:
 *
 *   { "error": { "code": "NOT_FOUND", "message": "...", "details": { ... } } }
 *
 * Returns null for non-API requests, so web pages keep Laravel's normal error pages.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->error(422, 'VALIDATION_FAILED', 'The given data was invalid.', $e->errors()),
            $e instanceof AuthenticationException => $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.'),
            $e instanceof InsufficientStockException => $this->error(409, 'INSUFFICIENT_STOCK', $e->getMessage()),
            $e instanceof ReservationConflictException => $this->error(409, 'RESERVATION_CONFLICT', $e->getMessage()),
            $e instanceof InvalidStatusTransitionException => $this->error(409, 'INVALID_STATUS_TRANSITION', $e->getMessage()),
            $e instanceof InventoryUnavailableException => $this->error(503, 'INVENTORY_UNAVAILABLE', 'Inventory service is temporarily unavailable. Please try again.'),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => $this->error(500, 'INTERNAL_ERROR', config('app.debug') ? $e->getMessage() : 'Something went wrong.'),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        [$code, $message] = match ($status) {
            403 => ['FORBIDDEN', 'You are not allowed to perform this action.'],
            404 => ['NOT_FOUND', 'The requested resource was not found.'],
            405 => ['METHOD_NOT_ALLOWED', 'This HTTP method is not allowed here.'],
            429 => ['TOO_MANY_REQUESTS', 'Too many requests. Please slow down.'],
            default => ['HTTP_ERROR', $e->getMessage() ?: 'Request failed.'],
        };

        return $this->error($status, $code, $message, headers: $e->getHeaders());
    }

    private function error(int $status, string $code, string $message, ?array $details = null, array $headers = []): JsonResponse
    {
        $body = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $body['details'] = $details;
        }

        return response()->json(['error' => $body], $status, $headers);
    }
}
