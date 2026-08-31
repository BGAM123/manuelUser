<?php

namespace App\Security;

use App\Service\ApiResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly ApiResponseFactory $apiResponse)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $message = $exception->getMessageKey();
        if ($message === 'Bad credentials.') {
            $message = 'Invalid credentials.';
        }

        return $this->apiResponse->error(
            $message,
            JsonResponse::HTTP_UNAUTHORIZED,
            null
        );
    }
}
