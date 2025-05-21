<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskState;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

final class TableOutputFactory implements ProgressOutputFactory
{
    public function create(OutputInterface $output, IpcHandlerRegistry $registry): TableOutput
    {
        $table = new TableOutput($output);
        $registry->onMessage(function (Message $message, TaskState $state) use ($table) {
            Debugger::log($message, 'onMessage');
            if ($message instanceof LogMessage) {
                $table->log($state, $message->message);
            }
        });
        return $table;
    }
}
