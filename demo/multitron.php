#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Multitron\Comms\TaskCommunicator;
use Multitron\Execution\Task;
use Multitron\Tree\Partition\PartitionedTask;
use Psr\Container\ContainerInterface;
use Multitron\Tree\TaskTreeBuilderFactory;
use Multitron\Orchestrator\TaskOrchestrator;
use Multitron\Execution\ProcessExecutionFactory;
use Multitron\Execution\Handler\DefaultIpcHandlerRegistryFactory;
use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use Multitron\Execution\Handler\ProgressServer;
use Multitron\Orchestrator\Output\TableOutputFactory;
use Multitron\Console\MultitronWorkerCommand;
use Multitron\Console\AbstractMultitronCommand;
use StreamIpc\NativeIpcPeer;
use Symfony\Component\Console\Application;
use Multitron\Tree\TaskTreeBuilder;

// IPC & execution setup
$ipc = new NativeIpcPeer();
$execFactory = new ProcessExecutionFactory($ipc);
$handlerFact = new DefaultIpcHandlerRegistryFactory(
    new MasterCacheServer(),
    new ProgressServer()
);
$outputFact = new TableOutputFactory();

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

$builderFact = new TaskTreeBuilderFactory($container);
$orchestrator = new TaskOrchestrator(
    $ipc,
    $execFactory,
    $outputFact,
    $handlerFact
);

$app = new Application('Multitron Demo', '1.0');
$app->add(new MultitronWorkerCommand($ipc));

class DemoCommand extends AbstractMultitronCommand {

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

$app->add(new DemoCommand($builderFact, $orchestrator));

$app->run();
