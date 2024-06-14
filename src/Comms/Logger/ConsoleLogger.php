<?php

declare(strict_types=1);

namespace Multitron\Comms\Logger;

use Override;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class ConsoleLogger implements TaskLogger
{
    private readonly OutputStyle $output;

    public function __construct(OutputInterface $output, private ?ConsoleSectionOutput $section = null)
    {
        $this->output = new SymfonyStyle(new StringInput(''), $output);
    }

    #[Override]
    public function error(Throwable $error): void
    {
        $content = $this->section?->getContent();
        $this->section?->clear();
        $this->output->error([$error->getMessage(), $error->getTraceAsString()]);
        $this->section?->write($content);
    }

    #[Override]
    public function info(string $taskId, string $message): void
    {
        $content = $this->section?->getContent();
        $this->section?->clear();
        $this->output->writeln([$taskId, $message]);
        $this->section?->write($content);
    }
}
