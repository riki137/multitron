<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Psr\Container\ContainerInterface;

class AppContainer implements ContainerInterface
{
    public function get(string $id): object
    {
        return new $id();
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
}
