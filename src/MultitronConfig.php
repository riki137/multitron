<?php

declare(strict_types=1);

namespace Multitron;

final class MultitronConfig
{
    public function __construct(
        public ?int $concurrency = null,
    )
    {
    }
}
