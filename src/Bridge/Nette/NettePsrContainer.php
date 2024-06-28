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
        return $this->container->getByType($id);
    }

    public function has($id): bool
    {
        return $this->container->hasService($id);
    }
}
