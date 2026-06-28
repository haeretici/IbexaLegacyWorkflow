<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Storage;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\OperationTriggerMapper;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;

class InMemoryWorkflowStorage implements WorkflowAdminStorageInterface
{
    private int $nextWorkflowId = 1;
    private int $nextTriggerId = 1;
    private int $nextEventId = 1;
    /** @var Trigger[] */
    private array $triggers = [];

    /** @var array<int, Workflow> */
    private array $workflows = [];

    /** @var array<int, WorkflowEvent[]> */
    private array $events = [];

    /** @var WorkflowProcess[] */
    private array $processes = [];

    private int $nextProcessId = 1;
    private int $transactionDepth = 0;

    public function listWorkflows(): array
    {
        return array_values($this->workflows);
    }

    public function listTriggers(): array
    {
        return array_values($this->triggers);
    }

    public function listProcesses(): array
    {
        return array_values($this->processes);
    }

    public function upsertWorkflow(Workflow $workflow): void
    {
        if ($workflow->id <= 0) {
            $workflow->id = $this->nextWorkflowId++;
        }
        $this->workflows[$workflow->id] = $workflow;
    }

    public function deleteWorkflow(int $id): void
    {
        unset($this->workflows[$id], $this->events[$id]);
    }

    public function upsertTrigger(Trigger $trigger): void
    {
        if ($trigger->id === null || $trigger->id <= 0) {
            $trigger->id = $this->nextTriggerId++;
        }
        $this->triggers[] = $trigger;
    }

    public function deleteTrigger(int $id): void
    {
        $this->triggers = array_values(array_filter(
            $this->triggers,
            static fn (Trigger $trigger): bool => $trigger->id !== $id
        ));
    }

    public function upsertWorkflowEvent(WorkflowEvent $event): void
    {
        if ($event->id <= 0) {
            $event->id = $this->nextEventId++;
        }

        $this->events[$event->workflowId] ??= [];
        foreach ($this->events[$event->workflowId] as $index => $existing) {
            if ($existing->id === $event->id) {
                $this->events[$event->workflowId][$index] = $event;

                return;
            }
        }

        $this->events[$event->workflowId][] = $event;
    }

    public function deleteWorkflowEvent(int $eventId, int $workflowId): void
    {
        if (!isset($this->events[$workflowId])) {
            return;
        }
        $this->events[$workflowId] = array_values(array_filter(
            $this->events[$workflowId],
            static fn (WorkflowEvent $event): bool => $event->id !== $eventId
        ));
    }

    public function assignTriggerToOperation(string $operation, string $connectType, int $workflowId): void
    {
        [$moduleName, $functionName, $triggerName, $connectLetter] = OperationTriggerMapper::mapWithConnectLetter($operation, $connectType);

        if ($workflowId === 0) {
            $this->triggers = array_values(array_filter(
                $this->triggers,
                static fn (Trigger $trigger): bool => !($trigger->name === $triggerName
                    && $trigger->moduleName === $moduleName
                    && $trigger->functionName === $functionName)
            ));

            return;
        }

        $this->upsertTrigger(new Trigger(null, $moduleName, $functionName, $connectLetter, $workflowId, $triggerName));
    }

    public function addTrigger(Trigger $trigger): void
    {
        $this->upsertTrigger($trigger);
    }

    public function addWorkflow(Workflow $workflow): void
    {
        $this->upsertWorkflow($workflow);
    }

    public function addWorkflowEvent(WorkflowEvent $event): void
    {
        $this->upsertWorkflowEvent($event);
    }

    public function findTrigger(string $name, string $moduleName, string $functionName): ?Trigger
    {
        foreach ($this->triggers as $trigger) {
            if ($trigger->name === $name
                && $trigger->moduleName === $moduleName
                && $trigger->functionName === $functionName
            ) {
                return $trigger;
            }
        }

        return null;
    }

    public function findWorkflow(int $id, int $version = 0): ?Workflow
    {
        $workflow = $this->workflows[$id] ?? null;
        if ($workflow === null) {
            return null;
        }

        if ($version !== 0 && $workflow->version !== $version) {
            return null;
        }

        return $workflow;
    }

    public function findWorkflowEvents(int $workflowId, int $version = 0): array
    {
        $events = $this->events[$workflowId] ?? [];

        return array_values(array_filter(
            $events,
            static fn (WorkflowEvent $event): bool => $event->version === $version
        ));
    }

    public function findWorkflowEvent(int $id, int $version = 0): ?WorkflowEvent
    {
        foreach ($this->events as $workflowEvents) {
            foreach ($workflowEvents as $event) {
                if ($event->id === $id && $event->version === $version) {
                    return $event;
                }
            }
        }

        return null;
    }

    public function findEventIdByPlacement(int $workflowId, int $placement, int $version = 0): ?int
    {
        foreach ($this->findWorkflowEvents($workflowId, $version) as $event) {
            if ($event->placement === $placement) {
                return $event->id;
            }
        }

        return null;
    }

    public function findProcessesByKey(string $processKey): array
    {
        return array_values(array_filter(
            $this->processes,
            static fn (WorkflowProcess $process): bool => $process->processKey === $processKey
        ));
    }

    public function storeProcess(WorkflowProcess $process): void
    {
        if ($process->id === null) {
            $process->id = $this->nextProcessId++;
            $process->created = time();
        }
        $process->modified = time();
        $this->processes[$process->id] = $process;
    }

    public function removeProcess(WorkflowProcess $process): void
    {
        if ($process->id !== null) {
            unset($this->processes[$process->id]);
        }
    }

    public function beginTransaction(): void
    {
        ++$this->transactionDepth;
    }

    public function commitTransaction(): void
    {
        if ($this->transactionDepth > 0) {
            --$this->transactionDepth;
        }
    }
}