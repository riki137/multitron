<?php

declare(strict_types=1);

namespace Multitron\Output;

use Amp\Cancellation;
use Amp\Future;
use Closure;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\Output;
use function Amp\async;
use function Amp\delay;

final class CombinedSectionOutputRenderer extends Output
{
    private ConsoleSectionOutput $section;

    private array $buffer = [];

    public function __construct(
        private readonly ConsoleOutputInterface $output,
        private readonly Closure $renderer,
        private readonly float $redrawInterval,
    ) {
        $this->section = $output->section();
        register_shutdown_function($this->doRender(...));
        ob_start();
        parent::__construct($output->getVerbosity(), $output->isDecorated(), $output->getFormatter());
    }

    protected function doWrite(string $message, bool $newline): void
    {
        if (!$newline) {
            $message = array_pop($this->buffer) . $message;
        }
        $this->buffer[] = $message;
    }

    public function render(Cancellation $cancel): Future
    {
        return async(function () use ($cancel) {
            while (!$cancel->isRequested()) {
                $this->doRender();
                delay($this->redrawInterval);
            }
        }, $cancel);
    }

    private function doRender(): void
    {
        $ob = ob_get_clean() ?: '';
        if ($ob !== '') {
            $this->buffer[] = $ob;
        }

        $table = ($this->renderer)();

        $this->section->clear();
        $this->output->writeln($this->buffer);
        if ($table !== null && $table !== '') {
            $this->section->write($table);
        }
        $this->buffer = [];

        ob_start();
    }
}
