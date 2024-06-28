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
        $stderr = implode(iterator_to_array($stderr));
        $warning = '';
        if (str_contains($stderr, 'Killed')) {
            $warning = ' <bg=red> OUT OF MEMORY? </>';
        }
        $message = '<fg=red>stderr</>: <fg=bright-red>"' . trim($stderr) . "\"</>$warning\n" .
            '<fg=yellow>stdout</>: <fg=bright-yellow>"' . trim(implode(iterator_to_array($stdout))) . "\"</>\n" .
            '<options=bold>Exception</>: ' . $previous->getMessage();

        parent::__construct($message, 0, $previous);
    }
}
