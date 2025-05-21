<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Multitron\Execution\Task;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

interface TaskNode
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return string[]
     */
    public function getDependencies(InputInterface $options): array;

    public function getChildren(TaskTreeBuilder $builder, InputInterface $options): void;

    /**
     * @return null|callable(): Task
     */
    public function getFactory(InputInterface $options): ?callable;
}
