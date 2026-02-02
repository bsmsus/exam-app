<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Application\Auth\PasswordService;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;

final class StudentAuthTest extends AuthenticatedWebTestCase
{
    public function test_student_can_register(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('user', $data);
        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayHasKey('refreshToken', $data);

        self::assertSame('Test Student', $data['user']['name']);
        self::assertSame('student@test.com', $data['user']['email']);
        self::assertSame('student', $data['user']['type']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $student = $em->getRepository(StudentEntity::class)
            ->findOneBy(['email' => 'student@test.com']);

        self::assertNotNull($student);
        self::assertSame('Test Student', $student->name);
    }

    public function test_student_registration_requires_all_fields(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Student',
                'email' => '',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Name, email and password are required', $data['error']);
    }

    public function test_student_registration_requires_minimum_password_length(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'password' => '12345',
            ])
        );

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Password must be at least 6 characters', $data['error']);
    }

    public function test_student_cannot_register_duplicate_email(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $student = StudentEntity::create(
            'Existing Student',
            'student@test.com',
            $passwordService->hash('password123')
        );

        $em->persist($student);
        $em->flush();

        $client->request(
            'POST',
            '/auth/student/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(409);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Email already registered', $data['error']);
    }

    public function test_student_can_login(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $student = StudentEntity::create(
            'Test Student',
            'student@test.com',
            $passwordService->hash('password123')
        );

        $em->persist($student);
        $em->flush();

        $client->request(
            'POST',
            '/auth/student/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'student@test.com',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('user', $data);
        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayHasKey('refreshToken', $data);

        self::assertSame('student@test.com', $data['user']['email']);
    }

    public function test_student_login_fails_with_wrong_password(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $student = StudentEntity::create(
            'Test Student',
            'student@test.com',
            $passwordService->hash('password123')
        );

        $em->persist($student);
        $em->flush();

        $client->request(
            'POST',
            '/auth/student/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'student@test.com',
                'password' => 'wrongpassword',
            ])
        );

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid credentials', $data['error']);
    }

    public function test_student_login_fails_with_nonexistent_email(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nonexistent@test.com',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid credentials', $data['error']);
    }

    public function test_student_can_refresh_token(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'password' => 'password123',
            ])
        );

        $registerData = json_decode($client->getResponse()->getContent(), true);
        $refreshToken = $registerData['refreshToken'];

        $client->request(
            'POST',
            '/auth/student/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refreshToken' => $refreshToken,
            ])
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayHasKey('refreshToken', $data);
        self::assertNotSame($refreshToken, $data['refreshToken']);
    }

    public function test_student_refresh_fails_with_invalid_token(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refreshToken' => 'invalid-token',
            ])
        );

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid or expired refresh token', $data['error']);
    }

    public function test_student_can_logout(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/student/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'password' => 'password123',
            ])
        );

        $registerData = json_decode($client->getResponse()->getContent(), true);
        $refreshToken = $registerData['refreshToken'];

        $client->request(
            'POST',
            '/auth/student/logout',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refreshToken' => $refreshToken,
            ])
        );

        self::assertResponseStatusCodeSame(204);

        $client->request(
            'POST',
            '/auth/student/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refreshToken' => $refreshToken,
            ])
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function test_student_cannot_use_admin_refresh_token(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/admin/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'password' => 'password123',
            ])
        );

        $adminData = json_decode($client->getResponse()->getContent(), true);
        $adminRefreshToken = $adminData['refreshToken'];

        $client->request(
            'POST',
            '/auth/student/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refreshToken' => $adminRefreshToken,
            ])
        );

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid or expired refresh token', $data['error']);
    }
}
