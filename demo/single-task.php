#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Comms\TaskCommunicator;
use Multitron\Console\TaskCommand;
use Multitron\Execution\Task;
use Multitron\Tree\TaskTreeBuilder;
use Symfony\Component\Console\Application;

$factory = new MultitronFactory(null);
$app = new Application('Multitron Minimal Demo', '1.0');

// this registers the background worker command that actually runs nodes
$app->add($factory->getWorkerCommand());

// --- A tiny task: does some work, reports progress, logs a line ---
final class HelloTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->progress->setTotal(3);
        for ($i = 1; $i <= 3; $i++) {
            sleep(1);
            $comm->log("Step $i/3: hello from HelloTask");
            $comm->progress->addDone();
        }
    }
}

// --- A console command that wires the task into a graph and triggers it ---
final class HelloCommand extends TaskCommand
{
    public function getName(): ?string { return 'demo:hello'; }

    public function getNodes(TaskTreeBuilder $builder): array
    {
        // A graph can have many nodes; here we return a single node.
        return [
            $builder->task('hello', fn() => new HelloTask()),
        ];
    }
}

$app->add(new HelloCommand($factory->getTaskCommandDeps()));
$app->run();
