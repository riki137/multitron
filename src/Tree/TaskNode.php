<?php

declare(strict_types=1);

namespace Multitron\Tree;

use Symfony\Component\Console\Input\InputInterface;

interface TaskNode
{
    public function getId(): string;

    /**
     * @return string[]
     */
    public function getDependencies(InputInterface $options): array;
}
