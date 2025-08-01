# Nette Integration

## Installation

First, require the necessary packages:

```bash
composer require riki137/mulitrtron contributte/psr11-container-interface
```

## Configuration

Register both the PSR-11 extension and the Multitron extension in your `neon` configuration to autowire all required services:

```neon
extensions:
    psr11: Contributte\Psr11\DI\Psr11ContainerExtension
    multitron: Multitron\Bridge\Nette\MultitronExtension
```
