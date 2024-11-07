<?php

declare(strict_types=1);

namespace Multitron\Container;

use Generator;
use Multitron\Console\MultitronConfig;
use Multitron\Container\Node\FilteringTaskNode;
use Multitron\Container\Node\TaskNode;
use Multitron\Container\Node\TaskNodeGroup;
use Multitron\Multitron;
use Psr\Container\ContainerInterface;
use RuntimeException;

abstract class TaskRootTree extends TaskSubtree
{
    public function buildCommand(?string $name = null, ?MultitronConfig $config = null): Multitron
    {
        return new Multitron($this, $config ?? $this->container->get(MultitronConfig::class), $name);
    }

    public function buildSubcommand(string $name, TaskNode $node, ?MultitronConfig $config = null): Multitron
    {
        return new Multitron($node, $config ?? $this->container->get(MultitronConfig::class), $name);
    }

    public function buildFilteredCommand(string $name, string $pattern, ?MultitronConfig $config = null): Multitron
    {
        return new Multitron(new FilteringTaskNode($this, $pattern), $config ?? $this->container->get(MultitronConfig::class), $name);
    }
}
