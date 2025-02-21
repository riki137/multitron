<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use LogicException;
use Multitron\Impl\Task;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * A task node that retrieves tasks from a PSR-11 container.
 */
final class ContainerTaskNode extends TaskNodeLeaf
{
    /**
     * @param string $id The unique identifier for this node
     * @param ContainerInterface $container The PSR-11 container
     * @param string $serviceId The service identifier to retrieve from the container
     *
     * @throws LogicException If parameters are empty or invalid
     */
    public function __construct(
        string $id,
        private readonly ContainerInterface $container,
        private readonly string $serviceId
    ) {
        if (empty($id) || empty($serviceId)) {
            throw new LogicException('Node ID and service ID cannot be empty');
        }

        parent::__construct($id);
    }

    /**
     * Retrieves and validates the task from the container.
     *
     * @return Task The task instance
     * @throws LogicException
     */
    public function getTask(): Task
    {
        try {
            $task = $this->container->get($this->serviceId);
        } catch (NotFoundExceptionInterface $e) {
            throw new LogicException(
                sprintf('Service "%s" not found in container', $this->serviceId),
                0,
                $e
            );
        } catch (ContainerExceptionInterface $e) {
            throw new LogicException(
                sprintf('Error retrieving service "%s" from container', $this->serviceId),
                0,
                $e
            );
        }

        if (!$task instanceof Task) {
            throw new LogicException(
                sprintf(
                    'Service "%s" (%s) must implement %s',
                    $this->serviceId,
                    get_class($task),
                    Task::class
                )
            );
        }

        return $task;
    }
}
