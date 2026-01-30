<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Application\Auth\JwtService;
use App\Infrastructure\Doctrine\AdminEntity;
use App\Infrastructure\Doctrine\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class AuthenticationListener
{
    private const PUBLIC_ROUTES = [
        '/auth/admin/register',
        '/auth/admin/login',
        '/auth/admin/refresh',
        '/auth/student/register',
        '/auth/student/login',
        '/auth/student/refresh',
    ];

    public function __construct(
        private JwtService $jwtService,
        private EntityManagerInterface $em
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip when user was already set (e.g. by test listener)
        if ($request->attributes->has('currentUser')) {
            return;
        }

        // Allow public routes
        if (in_array($path, self::PUBLIC_ROUTES, true)) {
            return;
        }

        // Allow OPTIONS requests (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return;
        }

        // Check if route requires authentication
        $requiresAdmin = str_starts_with($path, '/admin');
        $requiresStudent = str_starts_with($path, '/student');

        if (!$requiresAdmin && !$requiresStudent) {
            return;
        }

        // Extract token from Authorization header
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $event->setResponse(new JsonResponse(['error' => 'Missing or invalid authorization header'], 401));
            return;
        }

        $token = substr($authHeader, 7);
        $payload = $this->jwtService->validateAccessToken($token);

        if (!$payload) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid or expired token'], 401));
            return;
        }

        $userType = $payload['type'] ?? null;
        $userId = $payload['sub'] ?? null;

        // Verify user type matches route requirement
        if ($requiresAdmin && $userType !== 'admin') {
            $event->setResponse(new JsonResponse(['error' => 'Admin access required'], 403));
            return;
        }

        if ($requiresStudent && $userType !== 'student') {
            $event->setResponse(new JsonResponse(['error' => 'Student access required'], 403));
            return;
        }

        // Load user entity and attach to request
        if ($userType === 'admin') {
            $user = $this->em->find(AdminEntity::class, $userId);
        } else {
            $user = $this->em->find(StudentEntity::class, $userId);
        }

        if (!$user) {
            $event->setResponse(new JsonResponse(['error' => 'User not found'], 401));
            return;
        }

        // Attach user to request attributes for controllers to access
        $request->attributes->set('currentUser', $user);
        $request->attributes->set('currentUserType', $userType);
    }
}
