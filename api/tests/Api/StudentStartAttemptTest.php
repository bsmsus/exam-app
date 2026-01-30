<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class StudentStartAttemptTest extends AuthenticatedWebTestCase
{
    public function test_student_can_start_attempt_when_allowed(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $student = $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(
            Uuid::v4(),
            'Math',
            3,
            10
        );
        $em->persist($exam);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('attemptId', $data);

        $attempts = $em->getRepository(AttemptEntity::class)->findAll();
        self::assertCount(1, $attempts);

        $attempt = $attempts[0];
        self::assertSame($student->id->toRfc4122(), $attempt->student->id->toRfc4122());
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
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(
            Uuid::v4(),
            'Physics',
            3,
            60
        );
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-20 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-10 minutes');

        $em->persist($attempt);
        $em->flush();

        $this->requestAsStudent($client, 'POST', '/student/exams/' . $exam->id->toRfc4122() . '/start');

        self::assertResponseStatusCodeSame(409);

        self::assertCount(
            1,
            $em->getRepository(AttemptEntity::class)->findAll()
        );
    }
}
