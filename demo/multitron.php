#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTask;
use Psr\Container\ContainerInterface;
use Multitron\Bridge\Native\MultitronFactory;
use Symfony\Component\Console\Application;
use Multitron\Tree\TaskTreeBuilder;

// Minimal PSR-11 container stub
$container = new class implements ContainerInterface {
    public function get(string $id)
    {
        throw new RuntimeException("Service {$id} not found");
    }

    public function has(string $id): bool
    {
        return false;
    }
};

// Use MultitronFactory for all orchestration and dependencies
$factory = new MultitronFactory($container);

$app = new Application('Multitron Demo', '1.0');
$app->add($factory->getWorkerCommand());

class DemoCommand extends \Multitron\Console\TaskCommand {

    public function getName(): ?string
    {
        return 'demo:multitron';
    }

    public function getNodes(TaskTreeBuilder $b): array
    {
        $randomTask = fn() => new class extends PartitionedTask {
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
        };
        $screamerTask = fn() => new class implements Task {
            public function execute(TaskCommunicator $comm): void
            {
                $comm->progress->setTotal(3);
                for ($i = 0; $i < 3; $i++) {
                    usleep(400_000);
                    $comm->log('I LIKE TO SCREAM! (Scream no. ' . $i . ')');
                    $comm->progress->addDone();
                }
            }
        };
        return [
            $b->task('WakeUpDetermined', $randomTask),
            $b->task('MakeCoffeeLikeYouMeanIt', $randomTask, ['WakeUpDetermined']),
            $b->task('Scream', $screamerTask, ['MakeCoffeeLikeYouMeanIt']),
            $b->partitionedClosure('QuestionAllYourChoices', $randomTask, 4, ['WakeUpDetermined']),
            $b->task('PretendEverythingIsFine', $randomTask, ['MakeCoffeeLikeYouMeanIt', 'QuestionAllYourChoices']),
        ];
    }
}

// Use factory to provide TaskTreeBuilderFactory and TaskOrchestrator
$app->add(new DemoCommand($factory->getTaskCommandDeps()));

$app->run();
