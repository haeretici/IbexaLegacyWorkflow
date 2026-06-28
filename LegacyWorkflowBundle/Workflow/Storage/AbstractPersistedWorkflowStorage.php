<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Storage;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\OperationTriggerMapper;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;

abstract class AbstractPersistedWorkflowStorage implements WorkflowAdminStorageInterface
{
    /** @var Trigger[] */
    protected array $triggers = [];

    /** @var array<int, Workflow> */
    protected array $workflows = [];

    /** @var array<int, WorkflowEvent[]> */
    protected array $events = [];

    /** @var array<int, WorkflowProcess> */
    protected array $processes = [];

    protected int $nextWorkflowId = 1;
    protected int $nextTriggerId = 1;
    protected int $nextEventId = 1;
    protected int $nextProcessId = 1;
    protected int $transactionDepth = 0;

    public function __construct()
    {
        $data = $this->loadFromBackend();
        if (is_array($data)) {
            $this->importData($data);
        }
    }

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
        } elseif ($workflow->id >= $this->nextWorkflowId) {
            $this->nextWorkflowId = $workflow->id + 1;
        }

        $this->workflows[$workflow->id] = $workflow;
        $this->persist();
    }

    public function deleteWorkflow(int $id): void
    {
        unset($this->workflows[$id], $this->events[$id]);
        $this->triggers = array_values(array_filter(
            $this->triggers,
            static fn (Trigger $trigger): bool => $trigger->workflowId !== $id
        ));
        $this->persist();
    }

    public function upsertTrigger(Trigger $trigger): void
    {
        if ($trigger->id === null || $trigger->id <= 0) {
            $trigger->id = $this->nextTriggerId++;
        } elseif ($trigger->id >= $this->nextTriggerId) {
            $this->nextTriggerId = $trigger->id + 1;
        }

        $replaced = false;
        foreach ($this->triggers as $index => $existing) {
            if ($existing->name === $trigger->name
                && $existing->moduleName === $trigger->moduleName
                && $existing->functionName === $trigger->functionName
            ) {
                $this->triggers[$index] = $trigger;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $this->triggers[] = $trigger;
        }

        $this->persist();
    }

    public function deleteTrigger(int $id): void
    {
        $this->triggers = array_values(array_filter(
            $this->triggers,
            static fn (Trigger $trigger): bool => $trigger->id !== $id
        ));
        $this->persist();
    }

    public function upsertWorkflowEvent(WorkflowEvent $event): void
    {
        if ($event->id <= 0) {
            $event->id = $this->nextEventId++;
        } elseif ($event->id >= $this->nextEventId) {
            $this->nextEventId = $event->id + 1;
        }

        $this->events[$event->workflowId] ??= [];
        $replaced = false;
        foreach ($this->events[$event->workflowId] as $index => $existing) {
            if ($existing->id === $event->id) {
                $this->events[$event->workflowId][$index] = $event;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $this->events[$event->workflowId][] = $event;
        }

        $this->persist();
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
        $this->persist();
    }

    public function assignTriggerToOperation(string $operation, string $connectType, int $workflowId): void
    {
        [$moduleName, $functionName, $triggerName, $connectLetter] = $this->mapOperationToTrigger($operation, $connectType);

        if ($workflowId === 0) {
            foreach ($this->triggers as $index => $trigger) {
                if ($trigger->name === $triggerName
                    && $trigger->moduleName === $moduleName
                    && $trigger->functionName === $functionName
                ) {
                    unset($this->triggers[$index]);
                }
            }
            $this->triggers = array_values($this->triggers);
            $this->persist();

            return;
        }

        $this->upsertTrigger(new Trigger(
            id: null,
            moduleName: $moduleName,
            functionName: $functionName,
            connectType: $connectLetter,
            workflowId: $workflowId,
            name: $triggerName,
        ));
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
        } elseif ($process->id >= $this->nextProcessId) {
            $this->nextProcessId = $process->id + 1;
        }

        $process->modified = time();
        $this->processes[$process->id] = $process;
        $this->persist();
    }

    public function removeProcess(WorkflowProcess $process): void
    {
        if ($process->id !== null) {
            unset($this->processes[$process->id]);
            $this->persist();
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

        if ($this->transactionDepth === 0) {
            $this->persist();
        }
    }

    /** @return array<string, mixed> */
    protected function exportData(): array
    {
        $events = [];
        foreach ($this->events as $workflowId => $workflowEvents) {
            $events[$workflowId] = array_map(
                fn (WorkflowEvent $event): array => $this->serializeEvent($event),
                $workflowEvents
            );
        }

        return [
            'next_workflow_id' => $this->nextWorkflowId,
            'next_trigger_id' => $this->nextTriggerId,
            'next_event_id' => $this->nextEventId,
            'next_process_id' => $this->nextProcessId,
            'workflows' => array_map(fn (Workflow $workflow): array => $this->serializeWorkflow($workflow), $this->workflows),
            'events' => $events,
            'triggers' => array_map(fn (Trigger $trigger): array => $this->serializeTrigger($trigger), $this->triggers),
            'processes' => array_map(fn (WorkflowProcess $process): array => $this->serializeProcess($process), $this->processes),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function importData(array $data): void
    {
        $this->nextWorkflowId = (int) ($data['next_workflow_id'] ?? 1);
        $this->nextTriggerId = (int) ($data['next_trigger_id'] ?? 1);
        $this->nextEventId = (int) ($data['next_event_id'] ?? 1);
        $this->nextProcessId = (int) ($data['next_process_id'] ?? 1);

        $this->workflows = [];
        foreach ($data['workflows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $workflow = $this->hydrateWorkflow($row);
            $this->workflows[$workflow->id] = $workflow;
        }

        $this->events = [];
        foreach ($data['events'] ?? [] as $workflowId => $rows) {
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $event = $this->hydrateEvent($row);
                $this->events[(int) $workflowId][] = $event;
            }
        }

        $this->triggers = [];
        foreach ($data['triggers'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->triggers[] = $this->hydrateTrigger($row);
        }

        $this->processes = [];
        foreach ($data['processes'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $process = $this->hydrateProcess($row);
            $this->processes[$process->id] = $process;
        }
    }

    protected function persist(): void
    {
        if ($this->transactionDepth > 0) {
            return;
        }

        $this->persistToBackend($this->exportData());
    }

    /** @return array<string, mixed>|null */
    abstract protected function loadFromBackend(): ?array;

    /** @param array<string, mixed> $data */
    abstract protected function persistToBackend(array $data): void;

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function mapOperationToTrigger(string $operation, string $connectType): array
    {
        return OperationTriggerMapper::mapWithConnectLetter($operation, $connectType);
    }

    /** @param array<string, mixed> $row */
    private function hydrateWorkflow(array $row): Workflow
    {
        return new Workflow(
            id: (int) $row['id'],
            version: (int) ($row['version'] ?? 0),
            isEnabled: (bool) ($row['isEnabled'] ?? true),
            workflowTypeString: (string) ($row['workflowTypeString'] ?? 'group_ezserial'),
            name: (string) ($row['name'] ?? ''),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateEvent(array $row): WorkflowEvent
    {
        return new WorkflowEvent(
            id: (int) $row['id'],
            workflowId: (int) $row['workflowId'],
            workflowTypeString: (string) $row['workflowTypeString'],
            version: (int) ($row['version'] ?? 0),
            description: (string) ($row['description'] ?? ''),
            placement: (int) ($row['placement'] ?? 0),
            dataInt1: (int) ($row['dataInt1'] ?? 0),
            dataInt2: (int) ($row['dataInt2'] ?? 0),
            dataInt3: (int) ($row['dataInt3'] ?? 0),
            dataInt4: (int) ($row['dataInt4'] ?? 0),
            dataText1: (string) ($row['dataText1'] ?? ''),
            dataText2: (string) ($row['dataText2'] ?? ''),
            dataText3: (string) ($row['dataText3'] ?? ''),
            dataText4: (string) ($row['dataText4'] ?? ''),
            dataText5: (string) ($row['dataText5'] ?? ''),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateTrigger(array $row): Trigger
    {
        return new Trigger(
            id: isset($row['id']) ? (int) $row['id'] : null,
            moduleName: (string) $row['moduleName'],
            functionName: (string) $row['functionName'],
            connectType: (string) $row['connectType'],
            workflowId: (int) $row['workflowId'],
            name: (string) $row['name'],
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateProcess(array $row): WorkflowProcess
    {
        $process = new WorkflowProcess();
        $process->id = (int) $row['id'];
        $process->processKey = (string) ($row['processKey'] ?? '');
        $process->workflowId = (int) ($row['workflowId'] ?? 0);
        $process->userId = (int) ($row['userId'] ?? 0);
        $process->contentId = (int) ($row['contentId'] ?? 0);
        $process->contentVersion = (int) ($row['contentVersion'] ?? 0);
        $process->nodeId = (int) ($row['nodeId'] ?? 0);
        $process->eventId = (int) ($row['eventId'] ?? 0);
        $process->eventPosition = (int) ($row['eventPosition'] ?? 0);
        $process->status = (int) ($row['status'] ?? 0);
        $process->created = (int) ($row['created'] ?? 0);
        $process->modified = (int) ($row['modified'] ?? 0);
        $process->parameters = (array) ($row['parameters'] ?? []);
        $process->template = $row['template'] ?? null;
        $process->redirectUrl = $row['redirectUrl'] ?? null;

        return $process;
    }

    private function serializeWorkflow(Workflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'version' => $workflow->version,
            'isEnabled' => $workflow->isEnabled,
            'workflowTypeString' => $workflow->workflowTypeString,
            'name' => $workflow->name,
        ];
    }

    private function serializeEvent(WorkflowEvent $event): array
    {
        return [
            'id' => $event->id,
            'workflowId' => $event->workflowId,
            'workflowTypeString' => $event->workflowTypeString,
            'version' => $event->version,
            'description' => $event->description,
            'placement' => $event->placement,
            'dataInt1' => $event->dataInt1,
            'dataInt2' => $event->dataInt2,
            'dataInt3' => $event->dataInt3,
            'dataInt4' => $event->dataInt4,
            'dataText1' => $event->dataText1,
            'dataText2' => $event->dataText2,
            'dataText3' => $event->dataText3,
            'dataText4' => $event->dataText4,
            'dataText5' => $event->dataText5,
        ];
    }

    private function serializeTrigger(Trigger $trigger): array
    {
        return [
            'id' => $trigger->id,
            'moduleName' => $trigger->moduleName,
            'functionName' => $trigger->functionName,
            'connectType' => $trigger->connectType,
            'workflowId' => $trigger->workflowId,
            'name' => $trigger->name,
        ];
    }

    private function serializeProcess(WorkflowProcess $process): array
    {
        return [
            'id' => $process->id,
            'processKey' => $process->processKey,
            'workflowId' => $process->workflowId,
            'userId' => $process->userId,
            'contentId' => $process->contentId,
            'contentVersion' => $process->contentVersion,
            'nodeId' => $process->nodeId,
            'eventId' => $process->eventId,
            'eventPosition' => $process->eventPosition,
            'status' => $process->status,
            'created' => $process->created,
            'modified' => $process->modified,
            'parameters' => $process->parameters,
            'template' => $process->template,
            'redirectUrl' => $process->redirectUrl,
        ];
    }
}