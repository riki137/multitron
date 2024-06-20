<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Cancellation;
use Amp\Pipeline\Pipeline;
use Amp\Sync\Channel;
use Generator;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\SuccessMessage;
use Multitron\Comms\Data\Message\TaskProgress;
use Throwable;

class TaskCentre
{
    private TaskProgress $progress;

    private Pipeline $pipeline;

    public function __construct(Channel $channel, Cancellation $cancel)
    {
        $this->progress = new TaskProgress(0);
        $this->pipeline = Pipeline::fromIterable($this->pipeline($channel, $cancel));
    }

    private function pipeline(Channel $channel, Cancellation $cancel): Generator
    {
        while (true) {
            try {
                $message = $channel->receive($cancel);
                if ($message instanceof TaskProgress) {
                    $this->progress->update($message);
                }
                yield $message;
                if ($message instanceof SuccessMessage) {
                    return;
                }
            } catch (Throwable $e) {
                $this->progress->error++;
                yield new LogMessage($e->getMessage(), LogLevel::ERROR);
                return;
            }
        }
    }

    public function getProgress(): TaskProgress
    {
        return $this->progress;
    }

    public function getPipeline(): Pipeline
    {
        return $this->pipeline;
    }
}
