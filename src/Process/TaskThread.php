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
use Multitron\Error\ErrorHandler;
use Multitron\Error\WarningHandler;
use Multitron\Impl\Task;
use Nette\DI\Container;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;
use Tracy\Debugger;

class TaskThread implements AmpTask
{
    public const MEMORY_LIMIT = 'memory-limit';

    public const LOAD_CONTAINER = '__loadContainer__';

    private static ContainerInterface $container;

    public static bool $inThread = false;

    public function __construct(
        private readonly string $taskId,
        private readonly array $options = [],
        private readonly ?string $bootstrapPath = null
    ) {
    }

    public function run(Channel $channel, Cancellation $cancellation): int
    {
        self::$inThread = true;
        if (isset($this->options[self::MEMORY_LIMIT])) {
            ini_set('memory_limit', $this->options[self::MEMORY_LIMIT]);
        }


        if ($this->taskId === self::LOAD_CONTAINER) {
            $container = require $this->bootstrapPath;
            if ($container instanceof Container) {
                $container = $container->getByType(NettePsrContainer::class);
            }
            if (!$container instanceof ContainerInterface) {
                throw new RuntimeException('Container is not an instance of ' . ContainerInterface::class);
            }
            self::$container = $container;
            return 0;
        }

        try {
            $container = self::$container;
            $communicator = new TaskCommunicator($channel, $this->options);

            /** @var ErrorHandler $errorHandler */
            $errorHandler = $container->get(ErrorHandler::class);
            $warningHandler = new WarningHandler($communicator);
            set_error_handler($warningHandler->handle(...));

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

                gc_collect_cycles();
                ob_start();
                try {
                    $task->execute($communicator);
                } finally {
                    $output = ob_get_clean();
                    if (is_string($output) && trim($output) !== '') {
                        $communicator->log($output);
                    }
                }
                $communicator->sendProgress(true);
                $communicator->sendMessage(new SuccessMessage());
                $communicator->shutdown();
            } catch (Throwable $e) {
                $msg = $errorHandler->taskError($this->taskId, $e);
                try {
                    $communicator->error($msg);
                } catch (Throwable) {
                }
                $communicator->shutdown();
                return 1;
            }
        } catch (Throwable $e) {
            if (isset($errorHandler)) {
                $errorHandler->internalError($e);
            } else {
                Debugger::log($e);
            }
            return 1;
        }
        return 0;
    }
}
