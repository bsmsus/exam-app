<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ValidationExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ValidationExceptionListenerTest extends TestCase
{
    public function testConvertsValidationFailedExceptionToJsonResponse(): void
    {
        $violation = new ConstraintViolation('Title is required', '', [], null, 'title', null);
        $violations = new ConstraintViolationList([$violation]);
        $exception = new ValidationFailedException(new \stdClass(), $violations);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $listener = new ValidationExceptionListener();
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);

        $content = (string) $response->getContent();

        $this->assertJson($content);

        $data = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(['title' => ['Title is required']], $data['errors']);
        $this->assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());

    }
}
