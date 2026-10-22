<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    /**
     * Return a standardized success response.
     *
     * Response envelope: { success: true, message: string|null, data: mixed, meta: object|null, links: object|null }
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = [],
        array $links = []
    ): JsonResponse {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->additional([
                'success' => true,
                'message' => $message,
                'meta' => $meta ?: null,
                'links' => $links ?: null,
            ])->response()->setStatusCode($status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta ?: null,
            'links' => $links ?: null,
        ], $status);
    }

    /**
     * Return a standardized error response.
     *
     * Response envelope: { success: false, message: string, errors: object|null, data: mixed|null }
     */
    protected function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        mixed $data = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors ?: null,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a 404 not found response.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a 401 unauthorized response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a 403 forbidden response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a 422 validation error response.
     */
    protected function validationError(string $message = 'Validation failed', array $errors = []): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return a 201 created response.
     */
    protected function created(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a 200 deleted response.
     */
    protected function deleted(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->success(null, $message, Response::HTTP_OK);
    }

    /**
     * Return a 204 no content response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
