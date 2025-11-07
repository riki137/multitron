<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

require_once __DIR__ . '/../Mocks/Illuminate/Console/Application.php';

use Illuminate\Container\Container;
use Multitron\Bridge\Laravel\MultitronServiceProvider;
use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Console\TaskCommandDeps;
use Multitron\Console\WorkerCommand;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class LaravelServiceProviderIntegrationTest extends TestCase
{
    public function testRegisterBindsServices(): void
    {
        $app = new Container();
        $app->singleton(ContainerInterface::class, fn() => $app);
        $provider = new MultitronServiceProvider($app);
        $provider->register();

        $this->assertInstanceOf(MultitronFactory::class, $app->make(MultitronFactory::class));
        $this->assertInstanceOf(TaskCommandDeps::class, $app->make(TaskCommandDeps::class));
        $this->assertInstanceOf(WorkerCommand::class, $app->make(WorkerCommand::class));
    }
}
