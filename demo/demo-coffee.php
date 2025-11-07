#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Multitron\Bridge\Native\MultitronFactory;
use Multitron\Comms\TaskCommunicator;
use Multitron\Console\TaskCommand;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTask;
use Multitron\Tree\TaskTreeBuilder;
use Pimple\Psr11\Container;
use Symfony\Component\Console\Application;

/**
 * Container: map class names to factories.
 * In your app, replace with your framework DI.
 */
$container = new Container(new \Pimple\Container([
    BoilWaterTask::class => fn() => new BoilWaterTask(),
    BrewCoffeeTask::class => fn() => new BrewCoffeeTask(),
    QuestionChoicesTask::class => fn() => new QuestionChoicesTask(),
    PretendEverythingIsFineTask::class => fn() => new PretendEverythingIsFineTask(),
]));

$factory = new MultitronFactory($container);
$app = new Application('Multitron Coffee Demo', '1.0');
$app->add($factory->getWorkerCommand());

/**
 * Task 1: simple, linear work
 */
final class BoilWaterTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->progress->setTotal(5);
        for ($i = 1; $i <= 5; $i++) {
            usleep(150_000);
            $comm->log("Boiling... {$i}/5");
            $comm->progress->addDone();
        }
    }
}

/**
 * Task 2: depends on BoilWaterTask finishing
 */
final class BrewCoffeeTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->progress->setTotal(3);
        for ($i = 1; $i <= 3; $i++) {
            usleep(200_000);
            $comm->log("Brewing shot {$i}/3");
            $comm->progress->addDone();
        }
    }
}

/**
 * Task 3 (partitioned): runs as multiple shards in parallel.
 * - We simulate 1,000..2,000 tiny items.
 * - Each shard reports progress.
 * - Occasionally we record an "occurrence" called SKIP to show counters.
 */
final class QuestionChoicesTask extends PartitionedTask
{
    public function execute(TaskCommunicator $comm): void
    {
        $total = random_int(1000, 2000);      // keep output sane
        $comm->progress->setTotal($total);

        for ($i = 0; $i < $total; $i++) {
            usleep(random_int(100, 400));     // pretend per-item work
            $comm->progress->addDone();

            // randomly mark an occurrence to show how it looks in metrics
            if (random_int(0, 100) < 5) {
                $comm->progress->addOccurrence('SKIP');
            }
        }
    }
}

/**
 * Task 4: depends on both BrewCoffeeTask and QuestionChoicesTask
 */
final class PretendEverythingIsFineTask implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->progress->setTotal(3);
        for ($i = 1; $i <= 3; $i++) {
            usleep(300_000);
            $comm->log("Everything is fine. Definitely. {$i}/3");
            $comm->progress->addDone();
        }
    }
}

/**
 * Console command: defines the graph
 *
 * Graph:
 *   BoilWater -> BrewCoffee ----\
 *                                > PretendEverythingIsFine
 *   BoilWater -> QuestionChoices /
 */
final class CoffeeCommand extends TaskCommand
{
    public function getName(): ?string { return 'demo:coffee'; }

    public function getNodes(TaskTreeBuilder $builder): array
    {
        $boil = $builder->service(BoilWaterTask::class, dependencies: [], id: 'BoilWater');

        $brew = $builder->service(BrewCoffeeTask::class, dependencies: [$boil], id: 'BrewCoffee');

        // partitioned(..., $partitions, deps, name)
        $question = $builder->partitioned(
            QuestionChoicesTask::class,
            partitionCount: 4,
            dependencies: [$boil],
            id: 'QuestionChoices'
        );

        $pretend = $builder->service(
            PretendEverythingIsFineTask::class,
            dependencies: [$brew, $question],
            id: 'PretendEverythingIsFine'
        );

        // order doesn't matter; the engine uses the dependency edges
        return [$boil, $brew, $question, $pretend];
    }
}

$app->add(new CoffeeCommand($factory->getTaskCommandDeps()));
$app->run();
