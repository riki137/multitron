<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use Multitron\Execution\Task;
use Symfony\Component\Console\Input\InputInterface;

final readonly class ClosureTaskNode implements TaskNode
{
    private array $dependencies;

    /**
     * @param string $id
     * @param Closure(InputInterface): Task $factory
     * @param (string|TaskNode)[] $dependencies
 */
    public function __construct(private string $id, private Closure $factory, array $dependencies = [])
    {
        $this->dependencies = self::castDependencies($dependencies);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFactory(InputInterface $options): callable
    {
        return fn() => ($this->factory)($options);
    }

    public function getDependencies(InputInterface $options): array
    {
        return $this->dependencies;
    }

    public function getChildren(TaskTreeBuilder $builder, InputInterface $options): void
    {
    }

    public static function castDependencies(array $dependencies): array
    {
        return array_map(static fn($dependency) => $dependency instanceof TaskNode ? $dependency->getId() : $dependency, $dependencies);
    }
}
