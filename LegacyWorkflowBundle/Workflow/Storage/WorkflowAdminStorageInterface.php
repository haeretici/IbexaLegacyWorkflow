<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Storage;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;

interface WorkflowAdminStorageInterface extends WorkflowStorageInterface
{
    /** @return Workflow[] */
    public function listWorkflows(): array;

    /** @return Trigger[] */
    public function listTriggers(): array;

    /** @return WorkflowProcess[] */
    public function listProcesses(): array;

    public function upsertWorkflow(Workflow $workflow): void;

    public function deleteWorkflow(int $id): void;

    public function upsertTrigger(Trigger $trigger): void;

    public function deleteTrigger(int $id): void;

    public function upsertWorkflowEvent(WorkflowEvent $event): void;

    public function deleteWorkflowEvent(int $eventId, int $workflowId): void;

    public function assignTriggerToOperation(string $operation, string $connectType, int $workflowId): void;
}