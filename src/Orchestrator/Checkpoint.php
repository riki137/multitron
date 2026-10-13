<?php

declare(strict_types=1);

namespace Multitron\Orchestrator;

use RuntimeException;

/**
 * State of a failed run, so it can be resumed with --resume <id>.
 * Only written when a run fails; deleted when the resumed run succeeds.
 */
final class Checkpoint
{
    public const MAX_AGE = 7 * 86400;

    /**
     * @param string[] $completed IDs of tasks that already succeeded
     * @param array<string, mixed> $cache Snapshot of the master cache
     */
    private function __construct(
        public readonly string $id,
        public readonly string $command,
        public array $completed = [],
        public array $cache = [],
    ) {
    }

    public static function create(string $command): self
    {
        return new self(bin2hex(random_bytes(8)), $command);
    }

    public static function load(string $id, string $command): self
    {
        $file = self::path($id);
        $data = is_file($file) && self::isOwnedByMe($file) ? file_get_contents($file) : false;
        if ($data === false) {
            throw new RuntimeException("Checkpoint '$id' not found (expired or already finished).");
        }
        $data = unserialize($data);
        if (!is_array($data) || !is_string($data['command'] ?? null) || !is_array($data['completed'] ?? null) || !is_array($data['cache'] ?? null)) {
            throw new RuntimeException("Checkpoint '$id' is corrupted.");
        }
        if ($data['command'] !== $command) {
            throw new RuntimeException("Checkpoint '$id' belongs to command '{$data['command']}', not '$command'.");
        }
        /** @var string[] $completed */
        $completed = array_filter($data['completed'], 'is_string');
        /** @var array<string, mixed> $cache */
        $cache = $data['cache'];
        return new self($id, $command, $completed, $cache);
    }

    public function save(): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create checkpoint directory '$dir'.");
        }
        $file = self::path($this->id);
        $data = serialize(['command' => $this->command, 'completed' => $this->completed, 'cache' => $this->cache]);
        if (file_put_contents("$file.tmp", $data) === false || !rename("$file.tmp", $file)) {
            throw new RuntimeException("Cannot write checkpoint '$file'.");
        }
        // ponytail: GC runs only on failed runs; fine while failures are rare.
        foreach (glob($dir . '/*.ckpt') ?: [] as $old) {
            if (filemtime($old) < time() - self::MAX_AGE) {
                @unlink($old);
            }
        }
    }

    public function delete(): void
    {
        @unlink(self::path($this->id));
    }

    public static function path(string $id): string
    {
        // the ID comes from the CLI, never let it escape the checkpoint directory
        if ($id === '' || !ctype_xdigit($id)) {
            throw new RuntimeException("Invalid checkpoint ID '$id'.");
        }
        return self::dir() . "/$id.ckpt";
    }

    private static function dir(): string
    {
        return sys_get_temp_dir() . '/multitron';
    }

    /**
     * The temp dir is shared, so refuse to unserialize files planted by other users.
     */
    private static function isOwnedByMe(string $file): bool
    {
        return !function_exists('posix_geteuid') || fileowner($file) === posix_geteuid();
    }
}
