<?php
declare(strict_types=1);

namespace Multitron\Tests\Integration;

use PHPUnit\Framework\TestCase;

abstract class AbstractIpcTestCase extends TestCase
{
    protected static ?string $autoloadPath = null;

    /** @var list<string> */
    private array $createdScripts = [];

    public static function setUpBeforeClass(): void
    {
        self::$autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
    }

    protected function createWorkerScript(string $phpBody): string
    {
        if (!self::$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor autoload');
        }
        $path = sys_get_temp_dir() . '/worker_' . uniqid('', true) . '.php';
        $script = "<?php\nrequire " . var_export(self::$autoloadPath, true) . ";\n" . $phpBody;
        file_put_contents($path, $script);
        $this->createdScripts[] = $path;
        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdScripts as $file) {
            @unlink($file);
        }
        $this->createdScripts = [];
        parent::tearDown();
    }
}
