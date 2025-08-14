<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Bridge\Native\MultitronFactory;
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

        $this->assertTrue($builder->hasDefinition(MultitronFactory::class));
        $this->assertTrue($builder->hasDefinition(WorkerCommand::class));
        $this->assertTrue($builder->hasDefinition(TaskCommandDeps::class));

        $this->assertTrue($builder->hasAlias('multitron.factory'));
        $this->assertTrue($builder->hasAlias('multitron.worker_command'));
        $this->assertTrue($builder->hasAlias('multitron.task_command_deps'));

        $this->assertSame(WorkerCommand::class, $builder->getDefinition(WorkerCommand::class)->getClass());
        $this->assertSame(TaskCommandDeps::class, $builder->getDefinition(TaskCommandDeps::class)->getClass());
    }
}
