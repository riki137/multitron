<?php
declare(strict_types=1);

namespace Multitron\Comms\Data;

final class TaskInfo
{
    public function __construct(
        public array $dependencies = [],
    ) {
        $this->dependencies = array_map('mb_strtolower', $dependencies);
        $this->dependencies = array_combine($this->dependencies, $this->dependencies);
    }
}
