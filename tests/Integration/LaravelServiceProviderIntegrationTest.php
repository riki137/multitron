<?php
declare(strict_types=1);

namespace Illuminate\Console;

if (!class_exists(Application::class)) {
    class Application
    {
        public static function starting(callable $cb): void
        {
            $cb(new self());
        }

        public function resolveCommands(array $commands): void
        {
        }
    }
}

namespace Multitron\Tests\Integration;

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
