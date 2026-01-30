<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Generator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class OpenApiController
{
    #[Route('/openapi.json', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__)->name('*.php');

        $generator = new Generator();
        $openapi = $generator->generate($finder);

        return new JsonResponse(
            json_decode($openapi->toJson(), true),
            json: true
        );
    }
}
