<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class StudentExamIntegrationTest extends AuthenticatedWebTestCase
{
    public function test_student_can_view_exam_dashboard(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122());

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
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $client->request('GET', '/student/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(401);
    }

    public function test_admin_cannot_access_student_endpoints(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsAdmin($client, 'GET', '/student/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }

    public function test_student_dashboard_shows_nonexistent_exam_returns_404(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . Uuid::v4()->toRfc4122());

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Exam not found', $data['error']);
    }

    public function test_student_can_start_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('attemptId', $data);

        $attempt = $em->getRepository(AttemptEntity::class)->findOneBy([]);
        self::assertNotNull($attempt);
        self::assertSame(1, $attempt->attemptNumber);
        self::assertSame('IN_PROGRESS', $attempt->status);
        self::assertNotNull($attempt->startedAt);
        self::assertNull($attempt->endedAt);
    }

    public function test_student_cannot_start_attempt_for_nonexistent_exam(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . Uuid::v4()->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Exam not found', $data['error']);
    }

    public function test_student_cannot_start_attempt_when_one_is_in_progress(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'IN_PROGRESS';
        $attempt->startedAt = new \DateTimeImmutable();
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Attempt already in progress', $data['error']);
    }

    public function test_student_cannot_start_attempt_during_cooldown(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-30 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-10 minutes');
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('Your next attempt will be available at', $data['error']);
    }

    public function test_student_can_start_attempt_after_cooldown(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 10); // 10 minute cooldown
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-60 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-30 minutes'); // 30 mins ago, cooldown is 10
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(201);

        $attempts = $em->getRepository(AttemptEntity::class)->findAll();
        self::assertCount(2, $attempts);
    }

    public function test_student_cannot_start_attempt_when_max_attempts_reached(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 2, 0); // 2 max attempts, no cooldown
        $em->persist($exam);

        for ($i = 1; $i <= 2; $i++) {
            $attempt = new AttemptEntity();
            $attempt->id = Uuid::v4();
            $attempt->exam = $exam;
            $attempt->student = $student;
            $attempt->attemptNumber = $i;
            $attempt->status = 'COMPLETED';
            $attempt->startedAt = new \DateTimeImmutable("-{$i} hours");
            $attempt->endedAt = new \DateTimeImmutable("-{$i} hours +30 minutes");
            $em->persist($attempt);
        }
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('No attempts left', $data['error']);
    }

    public function test_student_can_submit_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'IN_PROGRESS';
        $attempt->startedAt = new \DateTimeImmutable('-10 minutes');
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/attempts/' . $attempt->id->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(204);

        $em->clear();
        $updatedAttempt = $em->find(AttemptEntity::class, $attempt->id);
        self::assertSame('COMPLETED', $updatedAttempt->status);
        self::assertNotNull($updatedAttempt->endedAt);
    }

    public function test_student_cannot_submit_nonexistent_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $this->requestAsStudent($client, 'POST', '/student/attempts/' . Uuid::v4()->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Attempt not found', $data['error']);
    }

    public function test_student_cannot_submit_another_students_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create another student
        $otherStudent = $this->createTestStudent('Other Student', 'other@test.com');

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);

        // Create attempt for other student
        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $otherStudent;
        $attempt->attemptNumber = 1;
        $attempt->status = 'IN_PROGRESS';
        $attempt->startedAt = new \DateTimeImmutable();
        $em->persist($attempt);
        $em->flush();

        // Restore original student for request
        $this->testStudent = $em->getRepository(\App\Infrastructure\Doctrine\StudentEntity::class)
            ->findOneBy(['email' => 'student@test.com']);
        $this->studentAccessToken = null; // Force token regeneration

        $this->requestAsStudent($client, 'POST', '/student/attempts/' . $attempt->id->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Attempt not found', $data['error']);
    }

    public function test_student_cannot_submit_already_completed_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-30 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-10 minutes');
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/attempts/' . $attempt->id->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid attempt state', $data['error']);
    }

    public function test_student_can_view_attempt_history(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam1 = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $exam2 = new ExamEntity(Uuid::v4(), 'Physics Exam', 3, 60);
        $em->persist($exam1);
        $em->persist($exam2);

        $attempt1 = new AttemptEntity();
        $attempt1->id = Uuid::v4();
        $attempt1->exam = $exam1;
        $attempt1->student = $student;
        $attempt1->attemptNumber = 1;
        $attempt1->status = 'COMPLETED';
        $attempt1->startedAt = new \DateTimeImmutable('-2 hours');
        $attempt1->endedAt = new \DateTimeImmutable('-1 hour');
        $em->persist($attempt1);

        $attempt2 = new AttemptEntity();
        $attempt2->id = Uuid::v4();
        $attempt2->exam = $exam2;
        $attempt2->student = $student;
        $attempt2->attemptNumber = 1;
        $attempt2->status = 'IN_PROGRESS';
        $attempt2->startedAt = new \DateTimeImmutable();
        $em->persist($attempt2);

        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/attempts');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data);

        // Most recent first
        self::assertSame('Physics Exam', $data[0]['examTitle']);
        self::assertSame('IN_PROGRESS', $data[0]['status']);
        self::assertSame('Math Exam', $data[1]['examTitle']);
        self::assertSame('COMPLETED', $data[1]['status']);
    }

    public function test_student_can_get_current_attempt(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'IN_PROGRESS';
        $attempt->startedAt = new \DateTimeImmutable();
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122() . '/current-attempt');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertNotNull($data['currentAttempt']);
        self::assertSame($attempt->id->toRfc4122(), $data['currentAttempt']['id']);
        self::assertSame(1, $data['currentAttempt']['attemptNumber']);
    }

    public function test_student_current_attempt_returns_null_when_none(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122() . '/current-attempt');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertNull($data['currentAttempt']);
    }

    public function test_student_dashboard_shows_correct_remaining_attempts(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 0); // 3 max, no cooldown
        $em->persist($exam);

        // Add 2 completed attempts
        for ($i = 1; $i <= 2; $i++) {
            $attempt = new AttemptEntity();
            $attempt->id = Uuid::v4();
            $attempt->exam = $exam;
            $attempt->student = $student;
            $attempt->attemptNumber = $i;
            $attempt->status = 'COMPLETED';
            $attempt->startedAt = new \DateTimeImmutable("-{$i} hours");
            $attempt->endedAt = new \DateTimeImmutable("-{$i} hours +30 minutes");
            $em->persist($attempt);
        }
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(1, $data['attemptsRemaining']);
        self::assertTrue($data['canStart']);
    }

    public function test_student_dashboard_shows_canStart_false_when_no_attempts_left(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 2, 0); // 2 max, no cooldown
        $em->persist($exam);

        // Add 2 completed attempts (max reached)
        for ($i = 1; $i <= 2; $i++) {
            $attempt = new AttemptEntity();
            $attempt->id = Uuid::v4();
            $attempt->exam = $exam;
            $attempt->student = $student;
            $attempt->attemptNumber = $i;
            $attempt->status = 'COMPLETED';
            $attempt->startedAt = new \DateTimeImmutable("-{$i} hours");
            $attempt->endedAt = new \DateTimeImmutable("-{$i} hours +30 minutes");
            $em->persist($attempt);
        }
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(0, $data['attemptsRemaining']);
        self::assertFalse($data['canStart']);
    }

    public function test_student_dashboard_shows_canStart_false_during_cooldown(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 60); // 60 min cooldown
        $em->persist($exam);

        // Add completed attempt 10 mins ago
        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-30 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-10 minutes');
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(2, $data['attemptsRemaining']);
        self::assertFalse($data['canStart']);
        self::assertNotNull($data['cooldownUntil']);
    }

    public function test_student_dashboard_shows_canStart_false_when_attempt_in_progress(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Math Exam', 3, 0);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'IN_PROGRESS';
        $attempt->startedAt = new \DateTimeImmutable();
        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'GET', '/student/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertFalse($data['canStart']);
    }
}
