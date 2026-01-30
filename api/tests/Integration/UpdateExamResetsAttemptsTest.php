<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Admin\UpdateExamService;
use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use App\Infrastructure\Doctrine\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateExamResetsAttemptsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\AttemptEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\ExamEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\StudentEntity')->execute();
    }

    public function test_exam_update_resets_attempts(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $service = static::getContainer()->get(UpdateExamService::class);

        $student = new StudentEntity(
            Uuid::v4(),
            'Test Student',
            'student@test.com',
            'dummy_hash'
        );
        $em->persist($student);

        $exam = new ExamEntity(Uuid::v4(), 'Math', 3, 60);
        $em->persist($exam);

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = 1;
        $attempt->status = 'COMPLETED';
        $attempt->startedAt = new \DateTimeImmutable();
        $attempt->endedAt = new \DateTimeImmutable();

        $em->persist($attempt);
        $em->flush();

        self::assertSame(
            1,
            $em->getRepository(AttemptEntity::class)->count([])
        );

        $service->updateExam($exam->id, 'Updated', 5, 30);

        self::assertSame(
            0,
            $em->getRepository(AttemptEntity::class)->count([])
        );
    }
}
