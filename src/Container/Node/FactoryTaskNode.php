<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Closure;
use InvalidArgumentException;
use Multitron\Impl\Task;
use RuntimeException;
use Throwable;

/**
 * A factory node that creates Task instances using a provided factory closure.
 */
class FactoryTaskNode extends TaskNodeLeaf
{
    /**
     * @param string $id The unique identifier for this node
     * @param Closure(): Task $factory The factory closure that creates Task instances
     * @throws InvalidArgumentException If the ID is empty
     */
    public function __construct(string $id, private readonly Closure $factory)
    {
        parent::__construct($id);
    }

    /**
     * Creates and returns a new Task instance using the factory closure.
     *
     * @return Task The created task instance
     * @throws RuntimeException If the factory fails to create a Task instance
     */
    public function getTask(): Task
    {
        try {
            $task = ($this->factory)();
            /** @var Task|mixed $task */
            if (!($task instanceof Task)) {
                throw new RuntimeException(
                    sprintf(
                        'Factory must return an instance of %s, got %s',
                        Task::class,
                        get_debug_type($task)
                    )
                );
            }

            return $task;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Failed to create Task instance: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
