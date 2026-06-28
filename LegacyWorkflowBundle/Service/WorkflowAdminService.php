<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Service;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\OperationTriggerMapper;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\WorkflowAdminStorageInterface;

final class WorkflowAdminService
{
    public function __construct(
        private readonly WorkflowAdminStorageInterface $storage,
        private readonly WorkflowEventTypeRegistry $eventTypeRegistry,
        private readonly WorkflowIniInspector $iniInspector,
    ) {
    }

    /** @return Workflow[] */
    public function listWorkflows(): array
    {
        return $this->storage->listWorkflows();
    }

    /** @return Trigger[] */
    public function listTriggers(): array
    {
        return $this->storage->listTriggers();
    }

    public function getWorkflow(int $id): ?Workflow
    {
        return $this->storage->findWorkflow($id);
    }

    /** @return WorkflowEvent[] */
    public function getWorkflowEvents(int $workflowId): array
    {
        $events = $this->storage->findWorkflowEvents($workflowId);
        usort($events, static fn (WorkflowEvent $a, WorkflowEvent $b): int => $a->placement <=> $b->placement);

        return $events;
    }

    public function createWorkflow(string $name): Workflow
    {
        $workflow = new Workflow(id: 0, name: $name);
        $this->storage->upsertWorkflow($workflow);

        return $workflow;
    }

    public function updateWorkflow(int $id, string $name, bool $isEnabled): ?Workflow
    {
        $workflow = $this->storage->findWorkflow($id);
        if ($workflow === null) {
            return null;
        }

        $workflow->name = $name;
        $workflow->isEnabled = $isEnabled;
        $this->storage->upsertWorkflow($workflow);

        return $workflow;
    }

    public function deleteWorkflow(int $id): void
    {
        $this->storage->deleteWorkflow($id);
    }

    public function addWorkflowEvent(int $workflowId, string $eventType, ?string $connectType = null): ?WorkflowEvent
    {
        if (!$this->isEventTypeSelectable($eventType, $connectType)) {
            return null;
        }

        $events = $this->getWorkflowEvents($workflowId);
        $placement = $events === [] ? 1 : (max(array_map(static fn (WorkflowEvent $e): int => $e->placement, $events)) + 1);

        $event = new WorkflowEvent(
            id: 0,
            workflowId: $workflowId,
            workflowTypeString: $eventType,
            placement: $placement,
        );
        $this->storage->upsertWorkflowEvent($event);

        return $event;
    }

    public function removeWorkflowEvent(int $workflowId, int $eventId): void
    {
        $this->storage->deleteWorkflowEvent($eventId, $workflowId);
    }

    /** @param array<string, mixed> $data */
    public function updateWorkflowEventData(int $workflowId, int $eventId, array $data): ?WorkflowEvent
    {
        $event = $this->storage->findWorkflowEvent($eventId);
        if ($event === null || $event->workflowId !== $workflowId) {
            return null;
        }

        $event->description = trim((string) ($data['description'] ?? $event->description));
        $event->dataText1 = (string) ($data['dataText1'] ?? $event->dataText1);
        $event->dataText2 = (string) ($data['dataText2'] ?? $event->dataText2);
        $event->dataText3 = (string) ($data['dataText3'] ?? $event->dataText3);
        $event->dataText4 = (string) ($data['dataText4'] ?? $event->dataText4);
        $event->dataText5 = (string) ($data['dataText5'] ?? $event->dataText5);
        $event->dataInt1 = (int) ($data['dataInt1'] ?? $event->dataInt1);
        $event->dataInt2 = (int) ($data['dataInt2'] ?? $event->dataInt2);
        $event->dataInt3 = (int) ($data['dataInt3'] ?? $event->dataInt3);
        $event->dataInt4 = (int) ($data['dataInt4'] ?? $event->dataInt4);

        $this->storage->upsertWorkflowEvent($event);

        return $event;
    }

    /** @param array<string, array<string, mixed>> $eventsData */
    public function updateWorkflowEventsData(int $workflowId, array $eventsData): void
    {
        foreach ($eventsData as $eventId => $data) {
            if (!is_array($data)) {
                continue;
            }

            $this->updateWorkflowEventData($workflowId, (int) $eventId, $data);
        }
    }

    public function assignTrigger(string $operation, string $connectType, int $workflowId): void
    {
        $this->storage->assignTriggerToOperation($operation, $connectType, $workflowId);
    }

    /** @return array<int, array{operation: string, connect_type: string, workflow_id: int, module: string, function: string}> */
    public function getTriggerMatrix(): array
    {
        $matrix = [];
        foreach ($this->iniInspector->getAvailableOperations() as $operation) {
            foreach (['before', 'after'] as $connectType) {
                $matrix[] = [
                    'operation' => $operation,
                    'connect_type' => $connectType,
                    'workflow_id' => $this->resolveAssignedWorkflowId($operation, $connectType),
                    'module' => $this->resolveModule($operation),
                    'function' => $this->resolveFunction($operation),
                ];
            }
        }

        return $matrix;
    }

    /** @return array<string, string> */
    public function getEventTypeChoices(?string $connectType = null): array
    {
        $choices = [];
        $candidateTypes = array_unique(array_merge(
            $this->iniInspector->getAvailableEventTypes(),
            $this->eventTypeRegistry->getRegisteredTypeStrings(),
        ));

        foreach ($candidateTypes as $typeString) {
            if (!$this->isEventTypeSelectable($typeString, $connectType)) {
                continue;
            }

            $type = $this->eventTypeRegistry->get($typeString);
            $choices[$typeString] = $type?->getName() ?? $typeString;
        }

        return $choices;
    }

    private function isEventTypeSelectable(string $eventType, ?string $connectType = null): bool
    {
        if (!$this->eventTypeRegistry->has($eventType)) {
            return false;
        }

        $iniAllowed = $this->iniInspector->getAvailableEventTypes();
        if (!in_array($eventType, $iniAllowed, true) && !str_starts_with($eventType, 'event_haeretici_')) {
            return false;
        }

        $type = $this->eventTypeRegistry->get($eventType);
        if ($type === null) {
            return false;
        }

        foreach ($this->iniInspector->getAvailableOperations() as $operation) {
            $parsed = OperationTriggerMapper::parseOperation($operation);
            $module = $parsed['module'];
            $function = $parsed['function'];

            if ($connectType === null) {
                if ($type->isAllowed($module, $function, 'before')
                    || $type->isAllowed($module, $function, 'after')) {
                    return true;
                }

                continue;
            }

            if ($type->isAllowed($module, $function, $connectType)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    public function listProcessesForAdmin(): array
    {
        $rows = [];
        foreach ($this->storage->listProcesses() as $process) {
            $workflow = $this->storage->findWorkflow($process->workflowId);
            $rows[] = [
                'id' => $process->id,
                'workflow_id' => $process->workflowId,
                'workflow_name' => $workflow?->name ?? (string) $process->workflowId,
                'content_id' => $process->contentId,
                'content_version' => $process->contentVersion,
                'status' => $process->status,
                'created' => $process->created,
                'modified' => $process->modified,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0));

        return $rows;
    }

    private function resolveAssignedWorkflowId(string $operation, string $connectType): int
    {
        [$module, $function, $triggerName] = $this->mapOperation($operation, $connectType);
        $trigger = $this->storage->findTrigger($triggerName, $module, $function);

        return $trigger?->workflowId ?? 0;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function mapOperation(string $operation, string $connectType): array
    {
        return OperationTriggerMapper::map($operation, $connectType);
    }

    private function resolveModule(string $operation): string
    {
        return $this->mapOperation($operation, 'before')[0];
    }

    private function resolveFunction(string $operation): string
    {
        return $this->mapOperation($operation, 'before')[1];
    }
}