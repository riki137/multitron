<?php

declare(strict_types=1);

namespace Multitron\Execution\Handler\MasterCache;

use PhpStreamIpc\Message\Message;

final class MasterCacheWriteRequest implements Message
{
    public const OP_WRITE = 1;
    public const OP_MERGE = 2;

    /**
     * @internal
     * Operation list:
     * [ [OP_WRITE|OP_MERGE, string[] $segments, mixed $value], … ]
     * @var array<array{int, string[], mixed}>
     */
    public array $ops = [];

    public function __construct()
    {
    }

    /**
     * Queue an overwrite at $path.
     *
     * @param string[] $path
     */
    public function write(array $path, mixed $value): self
    {
        $this->ops[] = [self::OP_WRITE, $path, $value,];
        return $this;
    }

    /**
     * Queue a deep‐merge at $path (arrays only).
     *
     * @param string[] $path
     */
    public function merge(array $path, array $value): self
    {
        $this->ops[] = [self::OP_MERGE, $path, $value];
        return $this;
    }
}
