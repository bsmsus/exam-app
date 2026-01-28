<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:openapi:generate')]
final class GenerateOpenApiCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $openapi = \OpenApi\scan([
            __DIR__ . '/../Controller',
        ]);

        file_put_contents(
            __DIR__ . '/../../public/openapi.json',
            $openapi->toJson()
        );

        $output->writeln('openapi.json generated');
        return Command::SUCCESS;
    }
}
