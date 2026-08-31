<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponseFactory
{
    public function success(mixed $data = null, int $status = Response::HTTP_OK, string $message = ''): JsonResponse
    {
        return $this->create(true, $status, $message, $data);
    }

    public function error(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $data = null): JsonResponse
    {
        return $this->create(false, $status, $message, $data);
    }

    private function create(bool $success, int $status, string $message, mixed $data): JsonResponse
    {
        $payload = [
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];

        return new JsonResponse($payload, $status);
    }
}
