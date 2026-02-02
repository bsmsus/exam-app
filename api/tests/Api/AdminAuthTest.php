<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Application\Auth\PasswordService;
use App\Infrastructure\Doctrine\Entity\AdminEntity;
use Doctrine\ORM\EntityManagerInterface;

final class AdminAuthTest extends AuthenticatedWebTestCase
{
    public function test_admin_can_register(): void
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

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('user', $data);
        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayHasKey('refreshToken', $data);
        self::assertSame('Test Admin', $data['user']['name']);
        self::assertSame('admin@test.com', $data['user']['email']);
        self::assertSame('admin', $data['user']['type']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(AdminEntity::class)->findOneBy(['email' => 'admin@test.com']);
        self::assertNotNull($admin);
        self::assertSame('Test Admin', $admin->name);
    }

    public function test_admin_registration_requires_all_fields(): void
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
                'email' => '',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Name, email and password are required', $data['error']);
    }

    public function test_admin_registration_requires_minimum_password_length(): void
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
                'password' => '12345',
            ])
        );

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Password must be at least 6 characters', $data['error']);
    }

    public function test_admin_cannot_register_duplicate_email(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $admin = AdminEntity::create(
            'Existing Admin',
            'admin@test.com',
            $passwordService->hash('password123')
        );
        $em->persist($admin);
        $em->flush();

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

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Email already registered', $data['error']);
    }

    public function test_admin_can_login(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $admin = AdminEntity::create(
            'Test Admin',
            'admin@test.com',
            $passwordService->hash('password123')
        );
        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/auth/admin/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'admin@test.com',
                'password' => 'password123',
            ])
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('user', $data);
        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayHasKey('refreshToken', $data);
        self::assertSame('admin@test.com', $data['user']['email']);
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        $client = static::createClient();
        $this->clearDatabase();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $passwordService = static::getContainer()->get(PasswordService::class);

        $admin = AdminEntity::create(
            'Test Admin',
            'admin@test.com',
            $passwordService->hash('password123')
        );
        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/auth/admin/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'admin@test.com',
                'password' => 'wrongpassword',
            ])
        );

        self::assertResponseStatusCodeSame(401);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid credentials', $data['error']);
    }

    public function test_admin_login_fails_with_nonexistent_email(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/admin/login',
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

    public function test_admin_can_refresh_token(): void
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

        $registerData = json_decode($client->getResponse()->getContent(), true);
        $refreshToken = $registerData['refreshToken'];

        $client->request(
            'POST',
            '/auth/admin/refresh',
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

    public function test_admin_refresh_fails_with_invalid_token(): void
    {
        $client = static::createClient();
        $this->clearDatabase();

        $client->request(
            'POST',
            '/auth/admin/refresh',
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

    public function test_admin_can_logout(): void
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

        $registerData = json_decode($client->getResponse()->getContent(), true);
        $refreshToken = $registerData['refreshToken'];

        $client->request(
            'POST',
            '/auth/admin/logout',
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
            '/auth/admin/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refreshToken' => $refreshToken,
            ])
        );

        self::assertResponseStatusCodeSame(401);
    }
}
