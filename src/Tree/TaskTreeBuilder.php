<?php
declare(strict_types=1);

namespace Multitron\Tree;

use Closure;
use LogicException;
use Multitron\Execution\Task;
use Psr\Container\ContainerInterface;

final class TaskTreeBuilder
{
    /** @var TaskNode[] top‐level definitions (leaf or group) */
    private array $nodes = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param string $id
     * @param Closure(): Task $factory
     * @param array $dependencies
     * @return $this
     */
    public function task(string $id, Closure $factory, array $dependencies = []): self
    {
        if (isset($this->nodes[$id])) {
            throw new LogicException("ID '$id' is already used");
        }
        $this->nodes[$id] = TaskNode::leaf($id, $factory, $dependencies);
        return $this;
    }

    /**
     * @param class-string $class
     * @param array $dependencies
     * @return $this
     */
    public function service(string $class, array $dependencies = []): self
    {
        return $this->task($class, fn() => $this->container->get($class), $dependencies);
    }

    /**
     * @param string $id
     * @param Closure(TaskTreeBuilder): void $cb
     * @param array $dependencies
     * @return $this
     */
    public function group(string $id, Closure $cb, array $dependencies = []): self
    {
        if (isset($this->nodes[$id])) {
            throw new LogicException("ID '$id' is already used");
        }
        // build subtree
        $sub = new self($this->container);
        $cb($sub);
        $this->nodes[$id] = TaskNode::group($id, $dependencies, $sub->nodes);
        return $this;
    }

    /**
     * @return TaskNode[] flat map of *leaf* nodes, each with
     *                   final deps only on leaf IDs
     * @throws LogicException on unknown IDs or cycles
     */
    public function build(): array
    {
        $leafDefs = [];  // id => ['factory'=>..., 'ownDeps'=>[],   'ancestors'=>[]]
        $groupDefs = [];  // id => ['declaredDeps'=>[], 'children'=>[ids...]]

        // 1) traverse once to fill both maps
        $walk = function (TaskNode $node, array $ancestors) use (&$walk, &$leafDefs, &$groupDefs) {
            $id = $node->id;
            if ($node->isLeaf()) {
                $leafDefs[$id] = [
                    'factory' => $node->factory,
                    'ownDeps' => $node->dependencies,
                    'ancestors' => $ancestors,
                ];
            } else {
                $groupDefs[$id] = [
                    'declaredDeps' => $node->dependencies,
                    'children' => array_map(fn($c) => $c->id, $node->children),
                ];
                // recurse, adding this group to the ancestor‐list
                foreach ($node->children as $child) {
                    $walk($child, array_merge($ancestors, [$id]));
                }
            }
        };

        foreach ($this->nodes as $n) {
            $walk($n, []);
        }

        // 2) build full member‐list for each group (recursive DFS)
        $members = [];  // groupId => leafId[]
        $collectMembers = function (string $gId) use (&$collectMembers, &$members, $groupDefs, $leafDefs) {
            if (isset($members[$gId])) {
                return $members[$gId];
            }
            if (!isset($groupDefs[$gId])) {
                throw new LogicException("Unknown group '$gId'");
            }
            $all = [];
            foreach ($groupDefs[$gId]['children'] as $cid) {
                if (isset($leafDefs[$cid])) {
                    $all[] = $cid;
                } else {
                    // child is a group
                    $all = array_merge($all, $collectMembers($cid));
                }
            }
            // dedupe and store
            return $members[$gId] = array_values(array_unique($all));
        };

        foreach (array_keys($groupDefs) as $g) {
            $collectMembers($g);
        }

        // 3) build the *final* set of leaf nodes with expanded deps
        $final = [];
        foreach ($leafDefs as $leafId => $info) {
            // inherit group‐declared deps
            $raw = array_merge(
                $info['ownDeps'],
                ...array_map(fn($g) => $groupDefs[$g]['declaredDeps'], $info['ancestors'])
            );
            $expanded = [];
            foreach ($raw as $dep) {
                if (isset($leafDefs[$dep])) {
                    $expanded[] = $dep;
                } elseif (isset($members[$dep])) {
                    $expanded = array_merge($expanded, $members[$dep]);
                } else {
                    throw new LogicException("Task '$leafId' depends on unknown '$dep'");
                }
            }
            $finalDeps = array_values(array_unique($expanded));
            $final[$leafId] = TaskNode::leaf(
                $leafId,
                $info['factory'],
                $finalDeps
            );
        }

        // 4) detect cycles on final leaf→leaf graph
        $this->detectCycles($final);

        return $final;
    }

    private function detectCycles(array $nodes): void
    {
        $visiting = [];
        $visited = [];
        $visit = function (string $id) use (&$visit, &$nodes, &$visiting, &$visited) {
            if (isset($visiting[$id])) {
                $cycle = implode(' → ', array_keys($visiting)) . " → $id";
                throw new LogicException("Cyclic dependency: $cycle");
            }
            if (isset($visited[$id])) {
                return;
            }
            $visiting[$id] = true;
            foreach ($nodes[$id]->dependencies as $d) {
                $visit($d);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($nodes) as $id) {
            $visit($id);
        }
    }
}
