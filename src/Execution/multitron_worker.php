#!/usr/bin/env php
<?php
declare(strict_types=1);

use Multitron\Message\ContainerLoadedMessage;
use Multitron\Message\StartTaskMessage;
use Multitron\Tree\Task;
use PhpStreamIpc\IpcPeer;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;

// 1. Parse CLI options
$options = getopt('', ['bootstrap:']);
if (!isset($options['bootstrap'])) {
    fwrite(STDERR, "Usage: php {$argv[0]} --bootstrap=/path/to/bootstrap.php\n");
    exit(1);
}
$bootstrapPath = $options['bootstrap'];
if (!is_file($bootstrapPath) || !is_readable($bootstrapPath)) {
    fwrite(STDERR, "Error: bootstrap file not found or not readable: {$bootstrapPath}\n");
    exit(1);
}

// 2. Load the container
/** @var ContainerInterface $container */
$container = require $bootstrapPath;
if (!$container instanceof ContainerInterface) {
    fwrite(STDERR, "Error: bootstrap did not return a PSR-11 ContainerInterface\n");
    exit(1);
}

$peer = $container->get(IpcPeer::class);
$session = $peer->connectToStdio();

// 3. Signal successful load
$session->notify(new ContainerLoadedMessage());


