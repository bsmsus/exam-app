<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class AdminCreateOrUpdateExamTest extends AuthenticatedWebTestCase
{
    public function test_admin_create_exam(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();

        $this->requestAsAdmin($client, 'POST', '/admin/exams', [
            'title' => 'Math',
            'maxAttempts' => 3,
            'cooldownMinutes' => 60,
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('examId', $data);
    }

    public function test_update_exam_resets_attempts(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $this->createTestAdmin();
        $this->createTestStudent();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exam = ExamEntity::create('Old', 3, 60);
        $em->persist($exam);
        $em->flush();

        $this->requestAsAdmin($client, 'PUT', '/admin/exams/' . $exam->id->toRfc4122(), [
            'title' => 'New',
            'maxAttempts' => 5,
            'cooldownMinutes' => 30,
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            0,
            $em->getRepository(AttemptEntity::class)->count([])
        );
    }
}
