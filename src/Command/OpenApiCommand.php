<?php
/**
 * OpenApiCommand — 生成 OpenAPI 文档命令。
 *
 * 用法: php webman attributes:openapi --output=public/openapi.json
 */
declare (strict_types=1);

namespace Vzina\Attributes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vzina\Attributes\OpenApi\Generator;

class OpenApiCommand extends Command
{
    protected static $defaultName = 'attributes:openapi';
    protected static $defaultDescription = 'Generate OpenAPI documentation from route attributes';

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path', 'public/openapi.json')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'API title', 'API Documentation')
            ->addOption('api-version', null, InputOption::VALUE_REQUIRED, 'API version', '1.0.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = $input->getOption('output');

        Generator::writeToFile($outputFile, [
            'title'   => $input->getOption('title'),
            'version' => $input->getOption('api-version'),
        ]);

        $output->writeln("<info>OpenAPI spec written to {$outputFile}</info>");
        return Command::SUCCESS;
    }
}
