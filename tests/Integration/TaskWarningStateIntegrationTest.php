<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Orchestrator\TaskWarningState;
use PHPUnit\Framework\TestCase;

final class TaskWarningStateIntegrationTest extends TestCase
{
    public function testAddSetAndMessageRoundTrip(): void
    {
        $state = new TaskWarningState();
        for ($i = 1; $i <= 6; $i++) {
            $state->add('bad' . $i, 1);
        }
        $state->set('oops', 3);

        $this->assertSame(9, $state->count());

        $expected = [
            ['messages' => ['bad1', 'bad2', 'bad3', 'bad4', 'bad5'], 'count' => 6],
            ['messages' => ['oops'], 'count' => 3],
        ];
        $this->assertSame($expected, iterator_to_array($state->fetchAll()));

        $message = $state->toMessage();
        $copy = new TaskWarningState();
        $copy->fromMessage($message);
        $this->assertSame($expected, iterator_to_array($copy->fetchAll()));
    }
}
