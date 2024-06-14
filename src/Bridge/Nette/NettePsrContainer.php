<?php

declare(strict_types=1);

namespace Multitron\Bridge\Nette;

use Nette\DI\Container;
use Psr\Container\ContainerInterface;

class NettePsrContainer implements ContainerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function get($id): mixed
    {
        if ($this->container->hasService($id)) {
            return $this->container->getService($id);
        }

        return $this->container->getByType($id, false);
    }

    public function has($id): bool
    {
        return $this->container->hasService($id);
    }
}
