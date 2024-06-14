<?php

declare(strict_types=1);

namespace Multitron\Bridge\Nette;

use LogicException;
use Multitron\Container\Def\LazyTaskDefinition;
use Multitron\Container\TaskContainer;
use Multitron\Impl\Task;
use Multitron\Impl\TaskGroup;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use RuntimeException;

class MultitronNetteExtension extends CompilerExtension
{
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $builder->addDefinition($this->prefix('taskContainer'))
            ->setFactory(TaskContainer::class);

        $builder->addDefinition($this->prefix('psrContainer'))
            ->setFactory(NettePsrContainer::class);
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();

        $defs = [];
        $found = $builder->findByType(Task::class) + $builder->findByType(TaskGroup::class);
        foreach ($found as $def) {
            if (!$def instanceof ServiceDefinition) {
                throw new LogicException('Service def expected');
            }
            $id = $def->getName();
            $defs[$id] = $builder->addDefinition(null)
                ->setFactory(LazyTaskDefinition::class, [$id, [], new Reference($this->prefix('psrContainer'))]);
        }
        foreach ($builder->findByTag('mtDepends') as $id => $values) {
            $def = $defs[$id] ?? null;
            if (!$def instanceof ServiceDefinition) {
                throw new RuntimeException("Task $id is not instance of Task or TaskGroup");
            }
            $dependencies = [];
            foreach ($values as $dep) {
                if (is_string($dep)) {
                    if ($dep[0] === '@') {
                        $dependencies[] = substr($dep, 1);
                    } else {
                        $dependencies[] = $dep;
                    }
                }
            }
            $def->getCreator()->arguments[1] = $dependencies;
        }

        $builder->getDefinition($this->prefix('taskContainer'))
            ->setFactory(TaskContainer::class, [$defs, new Reference($this->prefix('psrContainer'))]);
    }
}
