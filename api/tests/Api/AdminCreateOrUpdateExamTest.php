<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminCreateOrUpdateExamTest extends WebTestCase
{
    public function test_admin_create_exam(): void
    {
        $client = static::createClient();

        $client->request('POST', '/admin/exams', [
            'json' => [
                'title' => 'Math',
                'maxAttempts' => 3,
                'cooldownMinutes' => 60,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $data);
    }

    public function test_update_exam_resets_attempts(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = new ExamEntity(Uuid::v4(), 'Old', 3, 10);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable('-10 minutes');
        $attempt->endedAt = new \DateTimeImmutable('-5 minutes');

        $em->persist($attempt);
        $em->flush();

        $client->request('PUT', '/admin/exams/' . $exam->id->toRfc4122(), [
            'json' => [
                'title' => 'New',
                'maxAttempts' => 5,
                'cooldownMinutes' => 30,
            ],
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $em->getRepository(AttemptEntity::class)->count([]));
    }
}
