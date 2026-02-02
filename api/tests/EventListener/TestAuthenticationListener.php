<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Infrastructure\Doctrine\Entity\AdminEntity;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test-only: in APP_ENV=test, "Bearer test-admin" / "Bearer test-student" attach
 * the first admin/student from the DB so tests can authenticate without real JWT.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
final class TestAuthenticationListener
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (($_ENV['APP_ENV'] ?? '') !== 'test') {
            return;
        }

        $request = $event->getRequest();
        $authHeader = $request->headers->get('Authorization');

        if ($authHeader === 'Bearer test-admin') {
            $admin = $this->em->getRepository(AdminEntity::class)->findOneBy([]);
            if ($admin) {
                $request->attributes->set('currentUser', $admin);
                $request->attributes->set('currentUserType', 'admin');
                return;
            }
        }

        if ($authHeader === 'Bearer test-student') {
            $student = $this->em->getRepository(StudentEntity::class)->findOneBy([]);
            if ($student) {
                $request->attributes->set('currentUser', $student);
                $request->attributes->set('currentUserType', 'student');
                return;
            }
        }

        // Only respond with 401 when they explicitly sent test token but user missing
        if ($authHeader === 'Bearer test-admin' || $authHeader === 'Bearer test-student') {
            $event->setResponse(new JsonResponse(['error' => 'Test user not found'], 401));
        }
    }
}
