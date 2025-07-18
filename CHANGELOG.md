# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]



## [1.0.0-beta5] - 2025-06-24
### Added
- better error reporting for subprocess crashes

## [1.0.0-beta4] - 2025-06-24

## [1.0.0-beta3] - 2025-06-24
### Added
- memory limit option
### Fixed
- Refactor TaskTreeQueue to implement IteratorAggregate and update task retrieval logic
- enhance documentation for clarity

## [1.0.0-beta2] - 2025-06-19
### Added
- TableRenderer occurence white color
- Code coverage badge

### Fixed
- Unhandled request (IpcHandlerRegistry was too late)

## [1.0.0-beta1] - 2025-06-18
- complete greenfield rewrite based on experience from the previous version

## [0.2.0] - 2025-02-21
### Added
- multiple trees support
- --concurrent option
- readSubsetsSorted request

## [0.1.2] - 2024-08-23
### Fixed
- handled missing services in container
- fixed nette psr container

## [0.1.1] - 2024-08-22
### Added
- memory usage from `ps` command (if available)
### Fixed
- optimized memory usage of ChannelClient
- phpstan lvl5 errors, ci checks added

## [0.1.0] - 2024-08-20
- After months of development, the first release of Multitron is here!

[Unreleased]: https://github.com/riki137/multitron/compare/1.0.0-beta5...master
[1.0.0-beta5]: https://github.com/riki137/multitron/compare/1.0.0-beta4...1.0.0-beta5
[1.0.0-beta4]: https://github.com/riki137/multitron/compare/1.0.0-beta3...1.0.0-beta4
[1.0.0-beta3]: https://github.com/riki137/multitron/compare/1.0.0-beta2...1.0.0-beta3
[1.0.0-beta2]: https://github.com/riki137/multitron/compare/1.0.0-beta1...1.0.0-beta2
[1.0.0-beta1]: https://github.com/riki137/multitron/compare/0.2.0...1.0.0-beta1
[0.2.0]: https://github.com/riki137/multitron/compare/0.1.2...0.2.0
[0.1.2]: https://github.com/riki137/multitron/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/riki137/multitron/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/riki137/multitron/releases/tag/0.1.0
