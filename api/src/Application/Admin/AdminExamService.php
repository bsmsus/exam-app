<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Http\Admin\CreateOrUpdateExamRequest;
use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Repository\AttemptRepository;
use App\Infrastructure\Doctrine\Repository\ExamRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class AdminExamService
{
    public function __construct(
        private ExamRepository $examRepository,
        private AttemptRepository $attemptRepository
    ) {}

    public function create(CreateOrUpdateExamRequest $dto): Uuid
    {
        $exam = ExamEntity::create(
            $dto->title,
            $dto->maxAttempts,
            $dto->cooldownMinutes
        );

        $this->examRepository->save($exam);

        return $exam->id;
    }

    public function update(Uuid $examId, CreateOrUpdateExamRequest $dto): void
    {
        $exam = $this->examRepository->get($examId);

        $exam->update(
            $dto->title,
            $dto->maxAttempts,
            $dto->cooldownMinutes
        );

        $this->attemptRepository->deleteByExam($exam);

        $this->examRepository->flush();
    }

    public function get(Uuid $examId): array
    {
        $exam = $this->examRepository->get($examId);

        return [
            'id' => $exam->id->toRfc4122(),
            'title' => $exam->title,
            'maxAttempts' => $exam->maxAttempts,
            'cooldownMinutes' => $exam->cooldownMinutes,
        ];
    }

    public function list(): array
    {
        return array_map(
            static fn(ExamEntity $e) => [
                'id' => $e->id->toRfc4122(),
                'title' => $e->title,
                'maxAttempts' => $e->maxAttempts,
                'cooldownMinutes' => $e->cooldownMinutes,
            ],
            $this->examRepository->findAll()
        );
    }

    public function listAttempts(Uuid $examId): array
    {
        $exam = $this->examRepository->get($examId);

        return array_map(
            static fn($a) => [
                'id' => $a->id->toRfc4122(),
                'studentId' => $a->student->id->toRfc4122(),
                'studentName' => $a->student->name,
                'studentEmail' => $a->student->email,
                'attemptNumber' => $a->attemptNumber,
                'status' => $a->status,
                'startedAt' => $a->startedAt->format(DATE_ATOM),
                'endedAt' => $a->endedAt?->format(DATE_ATOM),
            ],
            $this->attemptRepository->findByExamOrdered($exam)
        );
    }
}
