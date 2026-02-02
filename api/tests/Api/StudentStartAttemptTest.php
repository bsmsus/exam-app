<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;

final class StudentStartAttemptTest extends AuthenticatedWebTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createExam(
        string $title,
        int $maxAttempts,
        int $cooldownMinutes
    ): ExamEntity {
        $exam = ExamEntity::create(
            $title,
            $maxAttempts,
            $cooldownMinutes
        );

        $this->em()->persist($exam);
        $this->em()->flush();

        return $exam;
    }

    public function test_student_can_start_attempt_when_allowed(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $student = $this->createTestStudent();
        $exam = $this->createExam('Math', 3, 10);

        $this->requestAsStudent(
            $client,
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        self::assertResponseStatusCodeSame(201);

        $attempts = $this->em()
            ->getRepository(AttemptEntity::class)
            ->findAll();

        self::assertCount(1, $attempts);

        $attempt = $attempts[0];

        self::assertSame(
            $student->id->toRfc4122(),
            $attempt->student->id->toRfc4122()
        );
        self::assertSame(1, $attempt->attemptNumber);
        self::assertSame('IN_PROGRESS', $attempt->status);
        self::assertNotNull($attempt->startedAt);
        self::assertNull($attempt->endedAt);
    }

    public function test_student_cannot_start_attempt_during_cooldown(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $student = $this->createTestStudent();
        $exam = $this->createExam('Physics', 3, 60);

        $attempt = AttemptEntity::create(
            $exam,
            $student,
            1,
            'COMPLETED'
        );

        $attempt->startedAt = new \DateTimeImmutable('-20 minutes');
        $attempt->endedAt   = new \DateTimeImmutable('-10 minutes');

        $this->em()->persist($attempt);
        $this->em()->flush();

        $this->requestAsStudent(
            $client,
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        self::assertResponseStatusCodeSame(409);

        self::assertCount(
            1,
            $this->em()->getRepository(AttemptEntity::class)->findAll()
        );
    }
}
