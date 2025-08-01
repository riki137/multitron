<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Bridge\Symfony\MultitronExtension;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SymfonyMultitronExtensionIntegrationTest extends TestCase
{
    public function testExtensionRegistersDefinitions(): void
    {
        $builder = new ContainerBuilder();
        $extension = new MultitronExtension();
        $extension->load([], $builder);

        $this->assertTrue($builder->hasDefinition('multitron.factory'));
        $this->assertTrue($builder->hasDefinition('multitron.worker_command'));
        $this->assertTrue($builder->hasDefinition('multitron.task_command_deps'));

        $this->assertSame(WorkerCommand::class, $builder->getDefinition('multitron.worker_command')->getClass());
        $this->assertSame(TaskCommandDeps::class, $builder->getDefinition('multitron.task_command_deps')->getClass());
    }
}
