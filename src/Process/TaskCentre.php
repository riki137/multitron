<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\Pipeline\Pipeline;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use Generator;
use Multitron\Comms\Data\Message\LogLevel;
use Multitron\Comms\Data\Message\LogMessage;
use Multitron\Comms\Data\Message\SuccessMessage;
use Multitron\Comms\Data\Message\TaskProgress;
use Multitron\Comms\Server\ChannelRequest;
use Multitron\Comms\Server\ChannelServer;
use Throwable;

class TaskCentre
{
    private TaskProgress $progress;

    private Pipeline $pipeline;

    public function __construct(?Channel $channel, private readonly ChannelServer $server)
    {
        $this->progress = new TaskProgress(0);
        $this->pipeline = Pipeline::fromIterable($channel ? $this->pipeline($channel) : []);
    }

    private function pipeline(Channel $channel): Generator
    {
        while (true) {
            try {
                $message = $channel->receive();
                if ($message instanceof ChannelRequest) {
                    $response = $this->server->answer($message);
                    $channel->send($response);
                    continue;
                }
                if ($message instanceof TaskProgress) {
                    $this->progress->update($message);
                }
                yield $message;
                if ($message instanceof SuccessMessage) {
                    return;
                }
            } catch (ChannelException) {
                return;
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
