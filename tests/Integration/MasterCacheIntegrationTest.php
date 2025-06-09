<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use Multitron\Execution\Handler\MasterCache\MasterCacheServer;
use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class MasterCacheIntegrationTest extends TestCase
{
    private string $worker1;
    private string $worker2;
    private array $initialData;

    protected function setUp(): void
    {
        parent::setUp();

        $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoload) {
            $this->markTestSkipped('Could not locate vendor autoload');
        }

        $this->initialData = [
            'root' => [
                'layer1' => [
                    'numbers' => range(1, 1000),
                    'strings' => array_map(fn($i) => str_repeat('x', 100), range(1, 100)),
                ],
                'layer1_b' => str_repeat('y', 500),
            ],
        ];

        $this->worker1 = sys_get_temp_dir() . '/worker1_' . uniqid() . '.php';
        $script1 = <<<'PHP'
<?php
require %s;
use StreamIpc\NativeIpcPeer;
use Multitron\Comms\MasterCacheClient;
use StreamIpc\Message\LogMessage;
$peer = new NativeIpcPeer();
$session = $peer->createStdioSession();
$cache = new MasterCacheClient($session);
$data = %s;
$cache->write($data)->await();
$session->notify(new LogMessage('w1_written'));
usleep(200000);
$final = $cache->read(['root'])->await();
$session->notify(new LogMessage('w1_final:' . json_encode($final)));
PHP;
        file_put_contents(
            $this->worker1,
            sprintf($script1, var_export($autoload, true), var_export($this->initialData, true))
        );

        $this->worker2 = sys_get_temp_dir() . '/worker2_' . uniqid() . '.php';
        $script2 = <<<'PHP'
<?php
require %s;
use StreamIpc\NativeIpcPeer;
use Multitron\Comms\MasterCacheClient;
use StreamIpc\Message\LogMessage;
$peer = new NativeIpcPeer();
$session = $peer->createStdioSession();
$cache = new MasterCacheClient($session);
usleep(100000);
$data = $cache->read(['root'])->await();
$data['root']['layer1']['numbers'][0] = 99999;
$data['root']['layer1']['new'] = ['added' => true];
$cache->write($data)->await();
$session->notify(new LogMessage('w2_written'));
PHP;
        file_put_contents(
            $this->worker2,
            sprintf($script2, var_export($autoload, true))
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->worker1);
        @unlink($this->worker2);
        parent::tearDown();
    }

    private static function findFinal(array $logs): ?string
    {
        foreach ($logs as $msg) {
            if (str_starts_with($msg, 'w1_final:')) {
                return substr($msg, strlen('w1_final:'));
            }
        }
        return null;
    }

    public function testConcurrentSlaveProcessesModifyMasterCache(): void
    {
        $peer = new NativeIpcPeer();
        $server = new MasterCacheServer();

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc1 = proc_open([PHP_BINARY, $this->worker1], $descriptors, $pipes1);
        $this->assertIsResource($proc1, 'Failed to start worker1');
        [$stdin1, $stdout1, $stderr1] = $pipes1;
        $session1 = $peer->createStreamSession($stdin1, $stdout1, $stderr1);
        $session1->onRequest($server->handleRequest(...));

        $proc2 = proc_open([PHP_BINARY, $this->worker2], $descriptors, $pipes2);
        $this->assertIsResource($proc2, 'Failed to start worker2');
        [$stdin2, $stdout2, $stderr2] = $pipes2;
        $session2 = $peer->createStreamSession($stdin2, $stdout2, $stderr2);
        $session2->onRequest($server->handleRequest(...));

        $logs = [];
        $collector = function (Message $msg) use (&$logs) {
            if ($msg instanceof LogMessage) {
                $logs[] = $msg->message;
            }
        };
        $session1->onMessage($collector);
        $session2->onMessage($collector);

        $start = microtime(true);
        while (self::findFinal($logs) === null && (microtime(true) - $start) < 3.0) {
            $peer->tick(0.1);
        }

        proc_close($proc1);
        proc_close($proc2);

        $this->assertContains('w1_written', $logs);
        $this->assertContains('w2_written', $logs);

        $finalJson = self::findFinal($logs);
        $this->assertNotNull($finalJson, 'final data missing');
        $final = json_decode($finalJson, true);
        $expected = $this->initialData;
        $expected['root']['layer1']['numbers'][0] = 99999;
        $expected['root']['layer1']['new'] = ['added' => true];
        $this->assertSame($expected, $final);

        $w1 = array_search('w1_written', $logs, true);
        $w2 = array_search('w2_written', $logs, true);
        $wf = null;
        foreach ($logs as $i => $m) {
            if (str_starts_with($m, 'w1_final:')) {
                $wf = $i;
                break;
            }
        }
        $this->assertNotFalse($w1);
        $this->assertNotFalse($w2);
        $this->assertNotNull($wf);
        $this->assertLessThan($wf, $w2);
        $this->assertLessThan($w2, $w1);
    }
}
