<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

enum TaskStatus
{
    case RUNNING;
    case SUCCESS;
    case ERROR;
    case SKIP;
}
