<?php

declare(strict_types=1);

namespace Multitron\Container\Node;

use Multitron\Console\InputConfiguration;
use Symfony\Component\Console\Input\InputDefinition;

abstract class TaskNode
{
    private array $dependencies = [];

    private array $groups = [];

    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function dependsOn(TaskNode|string ...$dependencies): static
    {
        foreach ($dependencies as $dependency) {
            if ($dependency instanceof TaskNode) {
                $dependency = $dependency->id;
            }
            $this->dependencies[$dependency] = $dependency;
        }
        return $this;
    }

    public function removeDependency(TaskNode|string $dependency): static
    {
        if ($dependency instanceof TaskNode) {
            $dependency = $dependency->id;
        }
        unset($this->dependencies[$dependency]);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function belongsTo(TaskGroupNode|string $group): self
    {
        if ($group instanceof TaskGroupNode) {
            $group = $group->getId();
        }
        $this->groups[] = $group;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function configure(InputConfiguration $input): void
    {
    }
}
