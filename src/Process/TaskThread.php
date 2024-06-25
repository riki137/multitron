<?php
declare(strict_types=1);

namespace Multitron\Process;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task as AmpTask;
use Amp\Sync\Channel;
use Multitron\Bridge\Nette\NettePsrContainer;
use Multitron\Comms\Data\Message\SuccessMessage;
use Multitron\Comms\TaskCommunicator;
use Multitron\Container\Node\TaskTreeProcessor;
use Multitron\Impl\Task;
use Nette\DI\Container;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;
use Tracy\Debugger;

class TaskThread implements AmpTask
{
    public static bool $inThread = false;

    private static ?ContainerInterface $container = null;

    public function __construct(
        private readonly string $bootstrapPath,
        private readonly string $taskId
    ) {
    }

    public function run(Channel $channel, Cancellation $cancellation): int
    {
        if (!gc_enabled()) {
            gc_enable();
        }
        self::$inThread = true;
        try {
            $communicator = new TaskCommunicator($channel);

            $container = self::$container;
            if ($container === null) {
                $container = require $this->bootstrapPath;
                if ($container instanceof Container) {
                    $container = $container->getByType(NettePsrContainer::class);
                }
                if (!$container instanceof ContainerInterface) {
                    $communicator->error('Container is not an instance of ' . ContainerInterface::class);
                    throw new RuntimeException('Container is not an instance of ' . ContainerInterface::class);
                }
                self::$container = $container;
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
                gc_collect_cycles();
                $task->execute($communicator);
                $communicator->sendProgress(true);
                $communicator->sendMessage(new SuccessMessage());
                $communicator->shutdown();
            } catch (Throwable $e) {
                $communicator->error($e->getMessage());
                Debugger::log($e, 'softCrash');
            }
        } catch (Throwable $e) {
            Debugger::log($e, 'TaskThread');
        }
        gc_collect_cycles();
        return 0;
    }
}
