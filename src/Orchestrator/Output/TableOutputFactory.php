<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use InvalidArgumentException;
use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use StreamIpc\Message\ErrorMessage;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use Symfony\Component\Console\Output\OutputInterface;

final class TableOutputFactory implements ProgressOutputFactory
{
    public const OPTION_COLORS = 'colors';
    public const OPTION_INTERACTIVE = 'interactive';
    public const OPTION_LOW_MEMORY_WARNING = 'low-memory-warn';

    public const DEFAULT_LOW_MEMORY_WARNING = 1024;

    /**
     * @param array<string, mixed> $options
     */
    public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry, array $options): TableOutput
    {
        $colors = $options[self::OPTION_COLORS] ?? null;
        if ($colors !== null) {
            $output->setDecorated((bool)$colors);
        }

        $interactiveOpt = $options[self::OPTION_INTERACTIVE] ?? 'detect';
        if ($interactiveOpt === 'detect') {
            $interactive = self::isInteractive();
        } else {
            $interactive = filter_var($interactiveOpt, FILTER_VALIDATE_BOOLEAN);
        }

        $lowMemoryWarning = $options[self::OPTION_LOW_MEMORY_WARNING] ?? self::DEFAULT_LOW_MEMORY_WARNING;
        if (!is_int($lowMemoryWarning) || $lowMemoryWarning < 0) {
            throw new InvalidArgumentException('Low memory warning must be a positive integer.');
        }

        $table = new TableOutput($output, $taskList, $interactive, $lowMemoryWarning);
        $registry->onMessage(function (Message $message, TaskState $state) use ($table) {
            if ($message instanceof LogMessage) {
                $table->log($state, $message->message);
            }
            if ($message instanceof ErrorMessage) {
                $table->log($state, $message->toString());
            }
        });
        return $table;
    }

    private static function isInteractive(): bool
    {
        return function_exists('stream_isatty') ? @stream_isatty(STDOUT) : true;
    }
}
