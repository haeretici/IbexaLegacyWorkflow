<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Storage;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;

interface WorkflowStorageInterface
{
    public function findTrigger(string $name, string $moduleName, string $functionName): ?Trigger;

    public function findWorkflow(int $id, int $version = 0): ?Workflow;

    /** @return WorkflowEvent[] */
    public function findWorkflowEvents(int $workflowId, int $version = 0): array;

    public function findWorkflowEvent(int $id, int $version = 0): ?WorkflowEvent;

    public function findEventIdByPlacement(int $workflowId, int $placement, int $version = 0): ?int;

    /** @return WorkflowProcess[] */
    public function findProcessesByKey(string $processKey): array;

    public function storeProcess(WorkflowProcess $process): void;

    public function removeProcess(WorkflowProcess $process): void;

    public function beginTransaction(): void;

    public function commitTransaction(): void;
}