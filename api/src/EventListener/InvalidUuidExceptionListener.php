<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class InvalidUuidExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof \InvalidArgumentException) {
            return;
        }

        $message = $throwable->getMessage();

        // Check if it's a UUID-related error
        if (stripos($message, 'uuid') === false && stripos($message, 'Invalid UUID') === false) {
            return;
        }

        $response = new JsonResponse(
            ['error' => 'Invalid ID format'],
            JsonResponse::HTTP_BAD_REQUEST
        );
        $event->setResponse($response);
    }
}
