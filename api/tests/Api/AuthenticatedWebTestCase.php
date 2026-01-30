<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Application\Auth\JwtService;
use App\Application\Auth\PasswordService;
use App\Infrastructure\Doctrine\AdminEntity;
use App\Infrastructure\Doctrine\StudentEntity;
use App\Tests\Api\Exception\TestSetupException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected ?AdminEntity $testAdmin = null;
    protected ?StudentEntity $testStudent = null;
    protected ?string $adminAccessToken = null;
    protected ?string $studentAccessToken = null;

    protected function clearDatabase(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\RefreshTokenEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\AttemptEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\ExamEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\AdminEntity')->execute();
        $em->createQuery('DELETE FROM App\Infrastructure\Doctrine\StudentEntity')->execute();
    }

    protected function createTestAdmin(string $name = 'Test Admin', string $email = 'admin@test.com'): AdminEntity
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $admin = new AdminEntity(
            Uuid::v4(),
            $name,
            $email,
            $passwordService->hash('password123')
        );
        $em->persist($admin);
        $em->flush();

        $this->testAdmin = $admin;
        return $admin;
    }

    protected function createTestStudent(string $name = 'Test Student', string $email = 'student@test.com'): StudentEntity
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $student = new StudentEntity(
            Uuid::v4(),
            $name,
            $email,
            $passwordService->hash('password123')
        );
        $em->persist($student);
        $em->flush();

        $this->testStudent = $student;
        return $student;
    }

    protected function getAdminAccessToken(?AdminEntity $admin = null): string
    {
        $admin = $admin ?? $this->testAdmin;
        if (!$admin) {
            throw new TestSetupException('No admin created. Call createTestAdmin() first.');
        }

        $jwtService = static::getContainer()->get(JwtService::class);
        $this->adminAccessToken = $jwtService->createAccessToken($admin->id, 'admin', $admin->email);
        return $this->adminAccessToken;
    }

    protected function getStudentAccessToken(?StudentEntity $student = null): string
    {
        $student = $student ?? $this->testStudent;
        if (!$student) {
            throw new TestSetupException('No student created. Call createTestStudent() first.');
        }

        $jwtService = static::getContainer()->get(JwtService::class);
        $this->studentAccessToken = $jwtService->createAccessToken($student->id, 'student', $student->email);
        return $this->studentAccessToken;
    }

    protected function requestAsAdmin(
        KernelBrowser $client,
        string $method,
        string $uri,
        array $data = []
    ): void {
        $token = $this->adminAccessToken ?? $this->getAdminAccessToken();

        $client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $data ? json_encode($data) : null
        );
    }

    protected function requestAsStudent(
        KernelBrowser $client,
        string $method,
        string $uri,
        array $data = []
    ): void {
        $token = $this->studentAccessToken ?? $this->getStudentAccessToken();

        $client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $data ? json_encode($data) : null
        );
    }
}
