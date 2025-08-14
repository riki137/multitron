<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Bridge\Nette\MultitronExtension;
use Nette\DI\Compiler;
use Nette\DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;

final class NetteMultitronExtensionIntegrationTest extends TestCase
{
    public function testExtensionAddsDefinitions(): void
    {
        $builder = new ContainerBuilder();
        $compiler = new Compiler($builder);
        $extension = new MultitronExtension();
        $extension->setCompiler($compiler, 'multi');
        $extension->loadConfiguration();

        $this->assertTrue($builder->hasDefinition('multi.factory'));
        $this->assertTrue($builder->hasDefinition('multi.commandDeps'));
        $this->assertTrue($builder->hasDefinition('multi.workerCommand'));
    }
}
