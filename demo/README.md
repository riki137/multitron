# Multitron Demos

These examples show different ways to run Multitron tasks from a standalone PHP script.
Each demo is self-contained: you can run them directly from the command line and watch them produce colorful output with progress bars and logs.

---

## 1. Single Task Demo

The smallest possible Multitron setup:
one task, one command, no dependencies.

```bash
php demo/single-task.php demo:hello
```

You’ll see the `HelloTask` run, reporting progress and logging messages as it goes.
Good starting point if you just want to understand the basics of a `Task` and `TaskCommand`.

---

## 2. Coffee Workflow Demo

A slightly bigger example showing:

* **Multiple tasks** with dependencies
* A **partitioned task** running multiple shards in parallel
* Progress reporting and "occurrences" in action

```bash
php demo/demo-coffee.php demo:coffee
```

This simulates a playful pipeline:

```
     BoilWater → BrewCoffee ┐
                            ├─> PretendEverythingIsFine
BoilWater → QuestionChoices ┘
```

---

## 3. Original Multitron Demo

The original "kitchen sink" example: mixes partitioned tasks, random timing,
and logging to show parallel execution at scale.

```bash
php demo/multitron.php demo:multitron
```

This is more chaotic by design, so it’s better once you’re familiar with the basics.
