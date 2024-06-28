<?php

declare(strict_types=1);

namespace Multitron\Comms\Local;

final class LocalChannelPair
{
    public readonly LocalChannelCombination $parent;

    public readonly LocalChannelCombination $child;

    public function __construct()
    {
        $parent = new LocalChannel();
        $child = new LocalChannel();
        $this->parent = new LocalChannelCombination(receiveFrom: $child, sendTo: $parent);
        $this->child = new LocalChannelCombination(receiveFrom: $parent, sendTo: $child);
    }
}
