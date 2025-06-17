<?php

declare(strict_types=1);

namespace Multitron\Orchestrator\Output;

use Multitron\Execution\Handler\IpcHandlerRegistry;
use Multitron\Orchestrator\TaskList;
use Multitron\Orchestrator\TaskState;
use StreamIpc\Message\ErrorMessage;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use Symfony\Component\Console\Output\OutputInterface;

final class TableOutputFactory implements ProgressOutputFactory
{
    public function create(TaskList $taskList, OutputInterface $output, IpcHandlerRegistry $registry): TableOutput
    {
        $table = new TableOutput($output, $taskList);
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
}
