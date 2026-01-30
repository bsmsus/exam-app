<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Exam Management API',
    description: 'API for managing exams, students, and attempt tracking'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT Authorization header using Bearer scheme'
)]
#[OA\Tag(name: 'Admin Auth', description: 'Admin authentication endpoints')]
#[OA\Tag(name: 'Student Auth', description: 'Student authentication endpoints')]
#[OA\Tag(name: 'Admin Exams', description: 'Admin exam management endpoints')]
#[OA\Tag(name: 'Student Exams', description: 'Student exam and attempt endpoints')]
class OpenApiConfig
{
}
