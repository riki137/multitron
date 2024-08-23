<?php

declare(strict_types=1);

namespace Multitron\Bridge\Nette;

use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

class NettePsrContainer implements ContainerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function get($id): mixed
    {
        try {
            return $this->container->getByType($id, false) ?? $this->container->getByName($id);
        } catch (MissingServiceException $e) {
            throw new class($e->getMessage(), 0, $e) extends RuntimeException implements NotFoundExceptionInterface {
            };
        }
    }

    public function has($id): bool
    {
        return $this->container->hasService($id);
    }
}
