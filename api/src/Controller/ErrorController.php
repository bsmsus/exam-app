<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class ErrorController
{
  public function show(
    Request $request,
    FlattenException $exception
  ): JsonResponse {
    return new JsonResponse(
      ['error' => $exception->getMessage()],
      $exception->getStatusCode()
    );
  }
}
