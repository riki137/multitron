<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Cancellation;
use Amp\Pipeline\Pipeline;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use Generator;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\Message;
use Multitron\Comms\Data\Message\TaskProgress;
use UnexpectedValueException;

class TaskCentre
{
    private TaskProgress $progress;

    private string $status = 'Initializing';

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
                $this->process($message);
                yield $message;
            } catch (ChannelException) {
                return;
            }
        }
    }

    private function process(Message $message): void
    {
        if ($message instanceof TaskProgress) {
            $this->progress->update($message);
        } elseif ($message instanceof LogMessage) {
            $this->status = $message->status;
        } else {
            throw new UnexpectedValueException('Unknown message type ' . get_class($message));
        }
    }

    public function getProgress(): TaskProgress
    {
        return $this->progress;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPipeline(): Pipeline
    {
        return $this->pipeline;
    }
}
