<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task as AmpTask;
use Amp\Sync\Channel;
use Multitron\Bridge\Nette\NettePsrContainer;
use Multitron\Comms\TaskCommunicator;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Impl\Task;
use Nette\DI\Container;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;
use Tracy\Debugger;

class TaskThread implements AmpTask
{
    public function __construct(
        private readonly int $semaphoreKey,
        private readonly int $parcelKey,
        private readonly string $bootstrapPath,
        private readonly string $taskId
    ) {
    }

    #[Override] public function run(Channel $channel, Cancellation $cancellation): int
    {
        try {
            $sharedMemory = new SharedMemory($this->semaphoreKey, $this->parcelKey);
            $communicator = new TaskCommunicator($sharedMemory, $channel, $cancellation);

            $container = require $this->bootstrapPath;
            if ($container instanceof Container) {
                $container = $container->getByType(NettePsrContainer::class);
            }
            if (!$container instanceof ContainerInterface) {
                $communicator->error('Container is not an instance of ' . ContainerInterface::class);
                throw new RuntimeException('Container is not an instance of ' . ContainerInterface::class);
            }
            $taskTree = $container->get(TaskTreeProcessor::class);
            if (!$taskTree instanceof TaskTreeProcessor) {
                $communicator->error('TaskTree is missing');
                throw new RuntimeException('TaskTree is not an instance of ' . TaskTreeProcessor::class);
            }

            try {
                $task = $taskTree->get($this->taskId);
                if (!$task instanceof Task) {
                    throw new RuntimeException('Task is not an instance of ' . Task::class);
                }

                Debugger::$strictMode = false;
                $task->execute($communicator);
            } catch (Throwable $e) {
                $communicator->error($e->getMessage());
                Debugger::log($e, 'softCrash');
            }
        } catch (Throwable $e) {
            Debugger::log($e, 'TaskThread');
        }
        return 0;
    }
}
