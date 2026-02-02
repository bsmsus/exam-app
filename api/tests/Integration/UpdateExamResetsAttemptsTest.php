<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Admin\AdminExamService;
use App\Http\Admin\CreateOrUpdateExamRequest;
use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UpdateExamResetsAttemptsTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM ' . AttemptEntity::class)->execute();
        $this->em->createQuery('DELETE FROM ' . ExamEntity::class)->execute();
        $this->em->createQuery('DELETE FROM ' . StudentEntity::class)->execute();
    }

    public function test_exam_update_resets_attempts(): void
    {
        $service = static::getContainer()->get(AdminExamService::class);

        $student = StudentEntity::create(
            'Test Student',
            'student@test.com',
            'dummy_hash'
        );
        $this->em->persist($student);

        $exam = ExamEntity::create('Math', 3, 60);
        $this->em->persist($exam);

        $attempt = AttemptEntity::create(
            $exam,
            $student,
            1,
            'COMPLETED'
        );

        $attempt->endedAt = new \DateTimeImmutable();

        $this->em->persist($attempt);
        $this->em->flush();

        self::assertSame(
            1,
            $this->em->getRepository(AttemptEntity::class)->count([])
        );

        $dto = new CreateOrUpdateExamRequest(
            'Updated',
            5,
            30
        );

        $service->update($exam->id, $dto);

        $this->em->clear();

        self::assertSame(
            0,
            $this->em->getRepository(AttemptEntity::class)->count([])
        );
    }
}
