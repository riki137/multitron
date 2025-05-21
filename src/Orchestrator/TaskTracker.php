<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

class TaskTracker
{
    /** @var array<string, TaskState> */
    private array $states = [];

    public function __construct()
    {
    }
}
