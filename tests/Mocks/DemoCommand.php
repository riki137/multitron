<?php
declare(strict_types=1);

namespace Multitron\Tests\Mocks;

use Multitron\Console\TaskCommand;
use Multitron\Tree\TaskTreeBuilder;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:demo')]
class DemoCommand extends TaskCommand
{
    public function getNodes(TaskTreeBuilder $b): array
    {
        return [
            $b->task('task', fn() => new DummyTask()),
        ];
    }
}

