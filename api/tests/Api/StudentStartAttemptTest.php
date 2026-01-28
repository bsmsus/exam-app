<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class StudentStartAttemptTest extends WebTestCase
{
    private function clearDatabase(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\AttemptEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\ExamEntity')->execute();
    }

    public function test_student_can_start_attempt_when_allowed(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Arrange: exam
        $exam = new ExamEntity(
            Uuid::v4(),
            'Math',
            3,
            10
        );
        $em->persist($exam);
        $em->flush();

        // Act: start attempt
        $client->request(
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        // Assert HTTP
        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('attemptId', $data);

        // Assert DB
        $attempts = $em->getRepository(AttemptEntity::class)->findAll();
        self::assertCount(1, $attempts);

        $attempt = $attempts[0];
        self::assertSame(1, $attempt->attemptNumber);
        self::assertSame('IN_PROGRESS', $attempt->status);
        self::assertNotNull($attempt->startedAt);
        self::assertNull($attempt->endedAt);
    }

    public function test_student_cannot_start_attempt_during_cooldown(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Arrange: exam
        $exam = new ExamEntity(
            Uuid::v4(),
            'Physics',
            3,
            60
        );
        $em->persist($exam);

        // Arrange: completed attempt 10 mins ago
        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-20 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-10 minutes');

        $em->persist($attempt);
        $em->flush();

        // Act
        $client->request(
            'POST',
            '/student/exams/' . $exam->id->toRfc4122() . '/start'
        );

        // Assert HTTP
        self::assertResponseStatusCodeSame(409);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Cooldown active', $data['error']);
        self::assertArrayHasKey('availableAt', $data);

        // Assert DB unchanged
        self::assertCount(
            1,
            $em->getRepository(AttemptEntity::class)->findAll()
        );
    }
}
