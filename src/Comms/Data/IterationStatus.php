<?php
declare(strict_types=1);

namespace Multitron\Comms\Data;

enum IterationStatus
{
    case DONE;
    case WARNING;
    case ERROR;
    case SKIPPED;
}
