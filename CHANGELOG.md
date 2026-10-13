# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `--resume <id>` - a run failed? Multitron now prints a ready-to-paste command that picks up right where it stopped. Finished tasks are skipped and the central cache is restored, so you never redo work

### Fixed
- Numeric task IDs like `"1"` work now, no more `TypeError` in the table output and no more queue spinning forever
- Duplicate task IDs fail loudly instead of silently eating one of your tasks
- `--low-memory-warn=N` accepts your values now (it used to reject all of them, even `0`)
- A crashed or OOM-killed worker is reported exactly once, TOTAL stays at 100% and the run exits with 1 instead of 0
- Workers killed by a signal report `128 + signal` (137 for SIGKILL) instead of a mysterious 255
- No more `Undefined constant SIGKILL` crash when `ext-pcntl` is not loaded
- No more `nan%` in the summary row when no tasks match your pattern
- README and demos work with Symfony Console 8 (`addCommands()` instead of the removed `add()`)

## [1.2.1] - 2026-08-12

### Fixed
- `MultitronFactory::setDefaultConcurrency()` rejects `0` right away instead of crashing later with an unrelated exception

## [1.2.0] - 2026-03-09

### Fixed
- table output shows memory usage on BusyBox environments without `ps -p`

## [1.1.0] - 2026-02-12

### Added
- `--no-deps` - run just the tasks your pattern matches, without dragging in their dependencies (also available as a flag on `PatternTaskNodeFactory` and `TaskTreeBuilder`)
- tag your tasks with the new optional `$tags` argument in `TaskTreeBuilder`
- `TaskNode` gets immutable setters, so you can derive modified nodes safely
- optional `TaskNodeFactory` for full control over how nodes get built

## [1.0.0] - 2025-11-07

### Added
- `IpcAdapter` - plug in your own IPC layer and make Multitron fit any environment

## [1.0.0-beta9] - 2025-08-14

### Added
- set the default concurrency straight from the `TaskCommandDeps` constructor
- the container dependency is optional, so Multitron now works without a DI container

## [1.0.0-beta8] - 2025-08-14

### Fixed
- Symfony integration

## [1.0.0-beta7] - 2025-08-13

### Added
- low memory table output in non-interactive mode, so CI logs stay readable
- low memory warning console option on `TaskCommand`

### Changed
- more reliable process exit code handling

## [1.0.0-beta6] - 2025-08-01

### Added
- `MultitronFactory` - set up Multitron in a few lines

### Changed
- `CommandDeps` is the only dependency `TaskCommand` needs
- individual framework extensions/bridges
- more reliable process exit code handling

## [1.0.0-beta5] - 2025-06-24

### Added
- clear error reports when a subprocess crashes

## [1.0.0-beta4] - 2025-06-24

## [1.0.0-beta3] - 2025-06-24

### Added
- memory limit option - keep individual tasks on a leash

## [1.0.0-beta2] - 2025-06-19

### Added
- occurrence counts in the table output are white now, easier to spot

### Fixed
- unhandled IPC request (`IpcHandlerRegistry` was registered too late)

## [1.0.0-beta1] - 2025-06-18

- complete greenfield rewrite based on experience from the previous version

## [0.2.0] - 2025-02-21

### Added
- multiple task trees in one run
- `--concurrent` option - choose how many tasks run at once
- `readSubsetsSorted` request

## [0.1.2] - 2024-08-23

### Fixed
- missing services in the container are handled
- Nette PSR container works

## [0.1.1] - 2024-08-22

### Added
- memory usage per task, read from `ps` (if available)

### Changed
- `ChannelClient` uses less memory

## [0.1.0] - 2024-08-20

- After months of development, the first release of Multitron is here!

[Unreleased]: https://github.com/riki137/multitron/compare/1.2.1...master
[1.2.1]: https://github.com/riki137/multitron/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/riki137/multitron/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/riki137/multitron/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/riki137/multitron/compare/1.0.0-beta9...1.0.0
[1.0.0-beta9]: https://github.com/riki137/multitron/compare/1.0.0-beta8...1.0.0-beta9
[1.0.0-beta8]: https://github.com/riki137/multitron/compare/1.0.0-beta7...1.0.0-beta8
[1.0.0-beta7]: https://github.com/riki137/multitron/compare/1.0.0-beta6...1.0.0-beta7
[1.0.0-beta6]: https://github.com/riki137/multitron/compare/1.0.0-beta5...1.0.0-beta6
[1.0.0-beta5]: https://github.com/riki137/multitron/compare/1.0.0-beta4...1.0.0-beta5
[1.0.0-beta4]: https://github.com/riki137/multitron/compare/1.0.0-beta3...1.0.0-beta4
[1.0.0-beta3]: https://github.com/riki137/multitron/compare/1.0.0-beta2...1.0.0-beta3
[1.0.0-beta2]: https://github.com/riki137/multitron/compare/1.0.0-beta1...1.0.0-beta2
[1.0.0-beta1]: https://github.com/riki137/multitron/compare/0.2.0...1.0.0-beta1
[0.2.0]: https://github.com/riki137/multitron/compare/0.1.2...0.2.0
[0.1.2]: https://github.com/riki137/multitron/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/riki137/multitron/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/riki137/multitron/releases/tag/0.1.0
