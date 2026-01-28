<?php

declare(strict_types=1);

namespace App\Command;

use OpenApi\Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'app:openapi:generate')]
final class GenerateOpenApiCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = Finder::create()->files()->in(__DIR__ . '/../Controller')->name('*.php');
        $openapi = (new Generator())->generate($finder);

        file_put_contents(
            __DIR__ . '/../../public/openapi.json',
            $openapi->toJson()
        );

        $output->writeln('openapi.json generated');
        return Command::SUCCESS;
    }
}
