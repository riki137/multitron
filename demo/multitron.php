#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Multitron\Comms\TaskCommunicator;
use Multitron\Console\TaskCommand;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTask;
use Multitron\Bridge\Native\MultitronFactory;
use Pimple\Psr11\Container;
use Symfony\Component\Console\Application;
use Multitron\Tree\TaskTreeBuilder;

// container stub for demo, you'll probably be using your framework's DI container
$container = new Container(new \Pimple\Container([
    RandomTask::class => fn() => new RandomTask(),
    Scream::class => fn() => new Scream(),
]));


$factory = new MultitronFactory($container);
$app = new Application('Multitron Demo', '1.0');
$app->add($factory->getWorkerCommand());

class RandomTask extends PartitionedTask
{
    public function execute(TaskCommunicator $comm): void
    {
        $total = random_int(1000, 5000);
        $comm->progress->setTotal($total);
        for ($i = 0; $i < $total; $i++) {
            usleep(random_int(100, 500));
            $comm->progress->addDone();
            if (random_int(0, 100) < 5) {
                $comm->progress->addOccurrence('SKIP');
            }
        }
    }
}

class Scream implements Task
{
    public function execute(TaskCommunicator $comm): void
    {
        $comm->progress->setTotal(3);
        for ($i = 0; $i < 3; $i++) {
            usleep(400_000);
            $comm->log('I LIKE TO SCREAM! (Scream no. ' . $i . ')');
            $comm->progress->addDone();
        }
    }
}

class DemoCommand extends TaskCommand
{
    public function getName(): ?string
    {
        return 'demo:multitron';
    }

    public function getNodes(TaskTreeBuilder $builder): array
    {
        return [
            $wakeUp = $builder->service(RandomTask::class, [], 'WakeUpDetermined'),
            $makeCoffee = $builder->service(RandomTask::class, [$wakeUp], 'MakeCoffeeLikeYouMeanIt'),
            $builder->service(Scream::class, [$makeCoffee]),
            $questionChoices = $builder->partitioned(RandomTask::class, 4, [$wakeUp], 'QuestionAllYourChoices'),
            $builder->service(RandomTask::class, [$makeCoffee, $questionChoices], 'PretendEverythingIsFine'),
        ];
    }
}

$app->add(new DemoCommand($factory->getTaskCommandDeps()));

$app->run();
