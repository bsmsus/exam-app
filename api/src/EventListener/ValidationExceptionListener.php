<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ValidationExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof ValidationFailedException) {
            $violations = $throwable->getViolations();
        } elseif ($throwable instanceof ConstraintViolationListInterface) {
            $violations = $throwable;
        } else {
            return;
        }

        $errors = [];
        $messages = [];

        foreach ($violations as $violation) {
            $path = (string) $violation->getPropertyPath() ?: 'body';
            $path = trim($path, '[]');
            $message = $violation->getMessage();
            $errors[$path][] = $message;
            $messages[] = sprintf('%s: %s', $path, $message);
        }

        $errorString = implode('; ', $messages);

        $response = new JsonResponse(['errors' => $errors, 'error' => $errorString], JsonResponse::HTTP_BAD_REQUEST);
        $event->setResponse($response);
    }
}
