<?php

declare(strict_types=1);

namespace Multitron\Console;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class InputConfiguration
{
    private array $options = [];

    /**
     * @param string $name
     * @param string|null $shortcut
     * @param int|null $mode {@see InputOption::VALUE_*}
     * @param string $description
     * @param mixed|null $default
     * @return $this
     */
    public function addOption(string $name, string $shortcut = null, int $mode = null, string $description = '', mixed $default = null): self
    {
        $this->options[] = new InputOption($name, $shortcut, $mode, $description, $default);
        return $this;
    }

    public function toDefinition(): InputDefinition
    {
        return new InputDefinition($this->options);
    }
}
