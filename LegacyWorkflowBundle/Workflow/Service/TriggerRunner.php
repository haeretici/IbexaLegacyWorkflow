<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\WorkflowStorageInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowStatus;

class TriggerRunner
{
    public function __construct(
        private readonly WorkflowStorageInterface $storage,
        private readonly WorkflowProcessRunner $processRunner,
        private readonly CurrentUserResolverInterface $currentUserResolver,
    ) {
    }

    /** @param array<string, mixed> $parameters @param string[]|null $keys @return array{Status: int, Result: mixed, WorkflowProcess?: WorkflowProcess} */
    public function runTrigger(string $name, string $moduleName, string $function, array $parameters, ?array $keys = null): array
    {
        $trigger = $this->storage->findTrigger($name, $moduleName, $function);
        if ($trigger === null) {
            return ['Status' => TriggerStatus::NO_CONNECTED_WORKFLOWS, 'Result' => null];
        }

        $workflow = $this->storage->findWorkflow($trigger->workflowId);
        if ($workflow === null || !$workflow->isEnabled) {
            return ['Status' => TriggerStatus::WORKFLOW_CANCELLED, 'Result' => null];
        }

        if ($keys !== null) {
            $keys[] = 'workflow_id';
        }

        $parameters['workflow_id'] = $trigger->workflowId;
        $parameters['trigger_name'] = $name;
        $parameters['module_name'] = $moduleName;
        $parameters['module_function'] = $function;
        $parameters['connect_type'] = $trigger->connectType === 'a' ? 'after' : 'before';

        if (!isset($parameters['user_id']) || (int) $parameters['user_id'] === 0) {
            $parameters['user_id'] = $this->currentUserResolver->getCurrentUserId();
        }

        $processKey = ProcessKeyBuilder::createKey($parameters, $keys);
        $workflowProcessList = $this->storage->findProcessesByKey($processKey);

        if ($workflowProcessList !== []) {
            $existing = $workflowProcessList[0];
            $status = $existing->status;

            if (in_array($status, [WorkflowStatus::STATUS_FAILED, WorkflowStatus::STATUS_CANCELLED, WorkflowStatus::STATUS_NONE, WorkflowStatus::STATUS_BUSY], true)) {
                $this->storage->removeProcess($existing);
                return ['Status' => TriggerStatus::WORKFLOW_CANCELLED, 'Result' => null];
            }

            if (in_array($status, [WorkflowStatus::STATUS_FETCH_TEMPLATE, WorkflowStatus::STATUS_FETCH_TEMPLATE_REPEAT, WorkflowStatus::STATUS_REDIRECT, WorkflowStatus::STATUS_RESET, WorkflowStatus::STATUS_DEFERRED_TO_CRON], true)) {
                return $this->runWorkflow($existing);
            }

            if ($status === WorkflowStatus::STATUS_DONE) {
                $this->storage->removeProcess($existing);
                return ['Status' => TriggerStatus::WORKFLOW_DONE, 'Result' => null];
            }

            return ['Status' => TriggerStatus::WORKFLOW_CANCELLED, 'Result' => null];
        }

        $workflowProcess = $this->createProcess($processKey, $parameters);
        $this->storage->storeProcess($workflowProcess);

        return $this->runWorkflow($workflowProcess);
    }

    /** @return array{Status: int, Result: mixed, WorkflowProcess?: WorkflowProcess} */
    public function runWorkflow(WorkflowProcess $workflowProcess): array
    {
        $workflow = $this->storage->findWorkflow($workflowProcess->workflowId);
        if ($workflow === null || !$workflow->isEnabled) {
            return ['Status' => TriggerStatus::WORKFLOW_CANCELLED, 'Result' => null];
        }

        $workflowStatus = $this->processRunner->run($workflowProcess, $workflow);

        $this->storage->beginTransaction();
        $this->storage->storeProcess($workflowProcess);

        if (in_array($workflowStatus, [WorkflowStatus::STATUS_FAILED, WorkflowStatus::STATUS_CANCELLED, WorkflowStatus::STATUS_NONE, WorkflowStatus::STATUS_BUSY], true)) {
            $this->storage->removeProcess($workflowProcess);
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::WORKFLOW_CANCELLED, 'Result' => null];
        }

        if ($workflowStatus === WorkflowStatus::STATUS_FETCH_TEMPLATE) {
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::FETCH_TEMPLATE, 'WorkflowProcess' => $workflowProcess, 'Result' => $this->buildTemplateResult($workflowProcess)];
        }

        if ($workflowStatus === WorkflowStatus::STATUS_FETCH_TEMPLATE_REPEAT) {
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::FETCH_TEMPLATE_REPEAT, 'WorkflowProcess' => $workflowProcess, 'Result' => $this->buildTemplateResult($workflowProcess)];
        }

        if ($workflowStatus === WorkflowStatus::STATUS_REDIRECT) {
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::REDIRECT, 'WorkflowProcess' => $workflowProcess, 'Result' => $workflowProcess->redirectUrl];
        }

        if ($workflowStatus === WorkflowStatus::STATUS_DEFERRED_TO_CRON) {
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::STATUS_CRON_JOB, 'WorkflowProcess' => $workflowProcess, 'Result' => ['content' => 'Deferred to cron.', 'path' => [['text' => 'Operation halt', 'url' => false]]]];
        }

        if ($workflowStatus === WorkflowStatus::STATUS_RESET) {
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::WORKFLOW_RESET, 'WorkflowProcess' => $workflowProcess, 'Result' => ['content' => 'Workflow was reset', 'path' => [['text' => 'Operation halt', 'url' => false]]]];
        }

        if ($workflowStatus === WorkflowStatus::STATUS_DONE) {
            $this->storage->removeProcess($workflowProcess);
            $this->storage->commitTransaction();
            return ['Status' => TriggerStatus::WORKFLOW_DONE, 'Result' => null];
        }

        $this->storage->commitTransaction();
        return ['Status' => TriggerStatus::WORKFLOW_CANCELLED, 'Result' => null];
    }

    /** @param array<string, mixed> $parameters */
    private function createProcess(string $processKey, array $parameters): WorkflowProcess
    {
        $process = new WorkflowProcess();
        $process->processKey = $processKey;
        $process->workflowId = (int) $parameters['workflow_id'];
        $process->userId = (int) ($parameters['user_id'] ?? 0);
        $process->contentId = (int) ($parameters['object_id'] ?? 0);
        $process->contentVersion = (int) ($parameters['version'] ?? 0);
        $process->nodeId = (int) ($parameters['node_id'] ?? 0);
        $process->parameters = $parameters;
        $process->created = time();
        $process->modified = time();

        return $process;
    }

    /** @return array{content: string, path?: array<int, array<string, mixed>>} */
    private function buildTemplateResult(WorkflowProcess $workflowProcess): array
    {
        $template = $workflowProcess->template ?? [];
        $result = ['content' => sprintf('Template: %s', $template['templateName'] ?? 'workflow/template.tpl')];
        if (isset($template['path'])) {
            $result['path'] = $template['path'];
        }

        return $result;
    }
}