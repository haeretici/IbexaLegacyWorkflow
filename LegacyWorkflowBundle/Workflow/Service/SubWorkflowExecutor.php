<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\WorkflowStorageInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowStatus;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;

class SubWorkflowExecutor
{
    private ?WorkflowProcessRunner $processRunner = null;

    public function __construct(
        private readonly WorkflowStorageInterface $storage,
    ) {
    }

    public function setProcessRunner(WorkflowProcessRunner $processRunner): void
    {
        $this->processRunner = $processRunner;
    }

    /** @param array<string, mixed> $childParameters */
    public function execute(
        WorkflowProcess $parentProcess,
        int $selectedWorkflowId,
        array $childParameters,
    ): int {
        if ($this->processRunner === null) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        $childProcessKey = ProcessKeyBuilder::createKey($childParameters);
        $childProcesses = $this->storage->findProcessesByKey($childProcessKey);
        $childProcess = $childProcesses[0] ?? $this->createChildProcess($childProcessKey, $childParameters);

        $childWorkflow = $this->storage->findWorkflow($selectedWorkflowId);
        if ($childWorkflow === null) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        $childEvent = null;
        if ($childProcess->eventId > 0) {
            $childEvent = $this->storage->findWorkflowEvent($childProcess->eventId);
        }

        $childStatus = $this->processRunner->run($childProcess, $childWorkflow, $childEvent);
        $this->storage->storeProcess($childProcess);

        return $this->mapChildWorkflowStatus($childStatus, $parentProcess, $childProcess);
    }

    private function mapChildWorkflowStatus(int $childStatus, WorkflowProcess $parentProcess, WorkflowProcess $childProcess): int
    {
        switch ($childStatus) {
            case WorkflowStatus::STATUS_DEFERRED_TO_CRON:
                $childProcess->status = WorkflowStatus::STATUS_WAITING_PARENT;
                $this->storage->storeProcess($childProcess);

                return WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON_REPEAT;
            case WorkflowStatus::STATUS_FETCH_TEMPLATE:
            case WorkflowStatus::STATUS_FETCH_TEMPLATE_REPEAT:
                $parentProcess->template = $childProcess->template;

                return WorkflowTypeStatus::STATUS_FETCH_TEMPLATE_REPEAT;
            case WorkflowStatus::STATUS_REDIRECT:
                $parentProcess->redirectUrl = $childProcess->redirectUrl;

                return WorkflowTypeStatus::STATUS_REDIRECT_REPEAT;
            case WorkflowStatus::STATUS_DONE:
                $this->storage->removeProcess($childProcess);

                return WorkflowTypeStatus::STATUS_ACCEPTED;
            case WorkflowStatus::STATUS_CANCELLED:
                $this->storage->removeProcess($childProcess);

                return WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED;
            case WorkflowStatus::STATUS_FAILED:
                $this->storage->removeProcess($childProcess);

                return WorkflowTypeStatus::STATUS_REJECTED;
            default:
                return $childProcess->eventStatus;
        }
    }

    /** @param array<string, mixed> $childParameters */
    private function createChildProcess(string $processKey, array $childParameters): WorkflowProcess
    {
        $childProcess = new WorkflowProcess();
        $childProcess->processKey = $processKey;
        $childProcess->workflowId = (int) $childParameters['workflow_id'];
        $childProcess->userId = (int) ($childParameters['user_id'] ?? 0);
        $childProcess->contentId = (int) ($childParameters['object_id'] ?? 0);
        $childProcess->contentVersion = (int) ($childParameters['version'] ?? 0);
        $childProcess->nodeId = (int) ($childParameters['node_id'] ?? 0);
        $childProcess->parameters = $childParameters;
        $childProcess->created = time();
        $childProcess->modified = time();
        $this->storage->storeProcess($childProcess);

        return $childProcess;
    }
}