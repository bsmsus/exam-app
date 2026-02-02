<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class AdminExamIntegrationTest extends AuthenticatedWebTestCase
{
    public function test_admin_create_exam_validates_input(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin($client, 'POST', '/admin/exams', [
            'title' => '',
            'maxAttempts' => 0,
            'cooldownMinutes' => -1,
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Title', $data['error']);
    }

    public function test_admin_create_exam_rejects_max_attempts_out_of_range(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin($client, 'POST', '/admin/exams', [
            'title' => 'Valid Title',
            'maxAttempts' => 9999,
            'cooldownMinutes' => 60,
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('maxAttempts', $data['error']);
    }

    public function test_admin_can_create_exam(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin($client, 'POST', '/admin/exams', [
            'title' => 'Math Exam',
            'maxAttempts' => 3,
            'cooldownMinutes' => 60,
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('examId', $data);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exam = $em->find(ExamEntity::class, Uuid::fromString($data['examId']));

        self::assertNotNull($exam);
        self::assertSame('Math Exam', $exam->title);
        self::assertSame(3, $exam->maxAttempts);
        self::assertSame(60, $exam->cooldownMinutes);
    }

    public function test_admin_create_exam_requires_authentication(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/admin/exams',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => 'Math Exam',
                'maxAttempts' => 3,
                'cooldownMinutes' => 60,
            ])
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function test_student_cannot_create_exam(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestStudent();

        $this->requestAsStudent($client, 'POST', '/admin/exams', [
            'title' => 'Math Exam',
            'maxAttempts' => 3,
            'cooldownMinutes' => 60,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_admin_can_list_exams(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam1 = ExamEntity::create('Math Exam', 3, 60);
        $exam2 = ExamEntity::create('Physics Exam', 5, 30);

        $em->persist($exam1);
        $em->persist($exam2);
        $em->flush();

        $this->requestAsAdmin($client, 'GET', '/admin/exams');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data);

        $titles = array_column($data, 'title');
        self::assertContains('Math Exam', $titles);
        self::assertContains('Physics Exam', $titles);
    }

    public function test_admin_can_get_single_exam(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = ExamEntity::create('Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsAdmin($client, 'GET', '/admin/exams/' . $exam->id->toRfc4122());

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($exam->id->toRfc4122(), $data['id']);
        self::assertSame('Math Exam', $data['title']);
        self::assertSame(3, $data['maxAttempts']);
        self::assertSame(60, $data['cooldownMinutes']);
    }

    public function test_admin_get_nonexistent_exam_returns_404(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin($client, 'GET', '/admin/exams/' . Uuid::v4()->toRfc4122());

        self::assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Exam not found', $data['error']);
    }

    public function test_admin_can_update_exam(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = ExamEntity::create('Old Title', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsAdmin(
            $client,
            'PUT',
            '/admin/exams/' . $exam->id->toRfc4122(),
            [
                'title' => 'New Title',
                'maxAttempts' => 5,
                'cooldownMinutes' => 30,
            ]
        );

        self::assertResponseStatusCodeSame(204);

        $em->clear();
        $updatedExam = $em->find(ExamEntity::class, $exam->id);

        self::assertSame('New Title', $updatedExam->title);
        self::assertSame(5, $updatedExam->maxAttempts);
        self::assertSame(30, $updatedExam->cooldownMinutes);
    }

    public function test_update_exam_resets_all_attempts(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();
        $student = $this->createTestStudent();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = ExamEntity::create('Math Exam', 3, 60);
        $em->persist($exam);

        $attempt1 = AttemptEntity::create($exam, $student, 1, 'COMPLETED');
        $attempt1->endedAt = new \DateTimeImmutable('-30 minutes');
        $em->persist($attempt1);

        $attempt2 = AttemptEntity::create($exam, $student, 2, 'IN_PROGRESS');
        $em->persist($attempt2);

        $em->flush();

        self::assertSame(2, $em->getRepository(AttemptEntity::class)->count([]));

        $this->requestAsAdmin(
            $client,
            'PUT',
            '/admin/exams/' . $exam->id->toRfc4122(),
            [
                'title' => 'Updated Math Exam',
                'maxAttempts' => 5,
                'cooldownMinutes' => 30,
            ]
        );

        self::assertResponseStatusCodeSame(204);

        $em->clear();

        self::assertSame(0, $em->getRepository(AttemptEntity::class)->count([]));
    }

    public function test_admin_can_get_exam_attempts(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();
        $student = $this->createTestStudent();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = ExamEntity::create('Math Exam', 3, 60);
        $em->persist($exam);

        $attempt = AttemptEntity::create($exam, $student, 1, 'COMPLETED');
        $attempt->startedAt = new \DateTimeImmutable('-30 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-10 minutes');

        $em->persist($attempt);
        $em->flush();

        $this->requestAsAdmin(
            $client,
            'GET',
            '/admin/exams/' . $exam->id->toRfc4122() . '/attempts'
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame($attempt->id->toRfc4122(), $data[0]['id']);
        self::assertSame($student->id->toRfc4122(), $data[0]['studentId']);
        self::assertSame('Test Student', $data[0]['studentName']);
        self::assertSame('student@test.com', $data[0]['studentEmail']);
        self::assertSame(1, $data[0]['attemptNumber']);
        self::assertSame('COMPLETED', $data[0]['status']);
        self::assertNotNull($data[0]['startedAt']);
        self::assertNotNull($data[0]['endedAt']);
    }

    public function test_admin_get_attempts_for_nonexistent_exam_returns_404(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin(
            $client,
            'GET',
            '/admin/exams/' . Uuid::v4()->toRfc4122() . '/attempts'
        );

        self::assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Exam not found', $data['error']);
    }

    public function test_admin_list_returns_empty_array_when_no_exams(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin($client, 'GET', '/admin/exams');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function test_admin_get_attempts_returns_empty_array_when_no_attempts(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = ExamEntity::create('Math Exam', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsAdmin(
            $client,
            'GET',
            '/admin/exams/' . $exam->id->toRfc4122() . '/attempts'
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }
}
