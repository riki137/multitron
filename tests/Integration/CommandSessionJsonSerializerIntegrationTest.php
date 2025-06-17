<?php
declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\JsonMessageSerializer;

final class CommandSessionJsonSerializerIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        $this->scriptPath = sys_get_temp_dir() . '/ipc_json_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\JsonMessageSerializer;

$peer = new NativeIpcPeer(new JsonMessageSerializer());
$session = $peer->createStdioSession();
$session->onRequest(fn(Message $m) => $m);
$peer->tick();
PHP;
        file_put_contents(
            $this->scriptPath,
            sprintf($script, var_export($autoloadPath, true))
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->scriptPath);
        parent::tearDown();
    }

    public function testEchoUsingJsonSerializer(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes);
        $this->assertIsResource($proc, 'Failed to start process');

        [$stdin, $stdout, $stderr] = $pipes;

        $peer = new NativeIpcPeer(new JsonMessageSerializer());
        $session = $peer->createStreamSession($stdin, $stdout, $stderr);

        $msg = new LogMessage('json-hello', 'info');
        $resp = $session->request($msg, 1.0)->await();

        $this->assertInstanceOf(LogMessage::class, $resp);
        $this->assertSame('json-hello', $resp->message);
        $this->assertSame('info', $resp->level);

        proc_close($proc);
    }
}
