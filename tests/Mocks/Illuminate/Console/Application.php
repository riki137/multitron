<?php
declare(strict_types=1);

namespace Illuminate\Console;

if (!class_exists(Application::class)) {
    class Application
    {
        public static function starting(callable $cb): void
        {
            $cb(new self());
        }

        public function resolveCommands(array $commands): void
        {
        }
    }
}
