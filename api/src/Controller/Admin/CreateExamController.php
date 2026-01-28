<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Doctrine\ExamEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/exams')]
final class CreateExamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * CREATE EXAM
     * POST /admin/exams
     */
    #[Route('', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $exam = new ExamEntity(
            Uuid::v4(),
            (string) $data['title'],
            (int) $data['maxAttempts'],
            (int) $data['cooldownMinutes']
        );

        $this->em->persist($exam);
        $this->em->flush();

        return new JsonResponse(
            ['id' => $exam->id->toRfc4122()],
            201
        );
    }
}
