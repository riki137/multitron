<?php

declare(strict_types=1);

namespace Multitron\Process;

use Amp\ByteStream\ReadableStream;
use Exception;
use Throwable;

class WorkerException extends Exception
{
    public function __construct(ReadableStream $stderr, ReadableStream $stdout, Throwable $previous)
    {
        $message = 'stderr: "' . implode(iterator_to_array($stderr)) . '", stdout: "' . implode(iterator_to_array($stdout)) . '" ' . $previous->getMessage();
        parent::__construct($message, 0, $previous);
    }
}
