<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class StudentExamIntegrationTest extends AuthenticatedWebTestCase
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
        $exam = ExamEntity::create($title, $maxAttempts, $cooldownMinutes);
        $this->em()->persist($exam);
        $this->em()->flush();

        return $exam;
    }

    public function test_student_can_view_exam_dashboard(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $exam = $this->createExam('Math Exam', 3, 60);

        $this->requestAsStudent(
            $client,
            'GET',
            '/student/exams/' . $exam->id->toRfc4122()
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame('Math Exam', $data['title']);
        self::assertSame(3, $data['maxAttempts']);
        self::assertSame(3, $data['attemptsRemaining']);
        self::assertSame(60, $data['cooldownMinutes']);
        self::assertTrue($data['canStart']);
    }

    public function test_student_dashboard_requires_authentication(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $exam = $this->createExam('Math Exam', 3, 60);

        $client->request(
            'GET',
            '/student/exams/' . $exam->id->toRfc4122()
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function test_admin_cannot_access_student_endpoints(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $exam = $this->createExam('Math Exam', 3, 60);

        $this->requestAsAdmin(
            $client,
            'GET',
            '/student/exams/' . $exam->id->toRfc4122()
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function test_student_dashboard_shows_nonexistent_exam_returns_404(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $this->requestAsStudent(
            $client,
            'GET',
            '/student/exams/' . Uuid::v4()->toRfc4122()
        );

        self::assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Exam not found', $data['error']);
    }

    public function test_student_can_start_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $exam = $this->createExam('Math Exam', 3, 60);

        $this->requestAsStudent(
            $client,
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        self::assertResponseStatusCodeSame(201);

        $attempt = $this->em()
            ->getRepository(AttemptEntity::class)
            ->findOneBy([]);

        self::assertNotNull($attempt);
        self::assertSame(1, $attempt->attemptNumber);
        self::assertSame('IN_PROGRESS', $attempt->status);
        self::assertNotNull($attempt->startedAt);
        self::assertNull($attempt->endedAt);
    }

    public function test_student_cannot_start_attempt_when_one_is_in_progress(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();

        $exam = $this->createExam('Math Exam', 3, 60);

        $attempt = AttemptEntity::create(
            $exam,
            $student,
            1,
            'IN_PROGRESS'
        );

        $this->em()->persist($attempt);
        $this->em()->flush();

        $this->requestAsStudent(
            $client,
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        self::assertResponseStatusCodeSame(409);
    }

    public function test_student_cannot_start_attempt_during_cooldown(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();

        $exam = $this->createExam('Math Exam', 3, 60);

        $attempt = AttemptEntity::create(
            $exam,
            $student,
            1,
            'COMPLETED'
        );

        $attempt->startedAt = new \DateTimeImmutable('-30 minutes');
        $attempt->endedAt   = new \DateTimeImmutable('-10 minutes');

        $this->em()->persist($attempt);
        $this->em()->flush();

        $this->requestAsStudent(
            $client,
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        self::assertResponseStatusCodeSame(409);
    }

    public function test_student_can_submit_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();

        $exam = $this->createExam('Math Exam', 3, 60);

        $attempt = AttemptEntity::create(
            $exam,
            $student,
            1,
            'IN_PROGRESS'
        );

        $attempt->startedAt = new \DateTimeImmutable('-10 minutes');

        $this->em()->persist($attempt);
        $this->em()->flush();

        $this->requestAsStudent(
            $client,
            'POST',
            '/student/attempts/' . $attempt->id->toRfc4122() . '/submit'
        );

        self::assertResponseStatusCodeSame(204);

        $this->em()->clear();

        $updated = $this->em()->find(AttemptEntity::class, $attempt->id);

        self::assertSame('COMPLETED', $updated->status);
        self::assertNotNull($updated->endedAt);
    }

    public function test_student_can_view_attempt_history(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();

        $exam1 = $this->createExam('Math Exam', 3, 60);
        $exam2 = $this->createExam('Physics Exam', 3, 60);

        $attempt1 = AttemptEntity::create($exam1, $student, 1, 'COMPLETED');
        $attempt1->startedAt = new \DateTimeImmutable('-2 hours');
        $attempt1->endedAt   = new \DateTimeImmutable('-1 hour');

        $attempt2 = AttemptEntity::create($exam2, $student, 1, 'IN_PROGRESS');

        $this->em()->persist($attempt1);
        $this->em()->persist($attempt2);
        $this->em()->flush();

        $this->requestAsStudent($client, 'GET', '/student/attempts');

        self::assertResponseStatusCodeSame(200);
    }
}
