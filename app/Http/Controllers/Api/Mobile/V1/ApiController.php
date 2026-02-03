<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\V1\ApiController as BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends BaseApiController
{
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
}
