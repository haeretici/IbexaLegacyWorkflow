<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\WorkflowStorageInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowStatus;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;
use Psr\Log\LoggerInterface;

class WorkflowProcessRunner
{
    public function __construct(
        private readonly WorkflowStorageInterface $storage,
        private readonly WorkflowEventTypeRegistry $eventTypeRegistry,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function run(
        WorkflowProcess $process,
        Workflow $workflow,
        ?WorkflowEvent $workflowEvent = null,
    ): int {
        if (!$workflow->isEnabled) {
            return WorkflowStatus::STATUS_CANCELLED;
        }

        $runCurrentEvent = true;
        $done = false;
        $workflowStatus = $process->status;
        $currentEventStatus = $process->eventStatus;

        if ($workflowEvent === null && $process->eventId > 0) {
            $workflowEvent = $this->storage->findWorkflowEvent($process->eventId);
        }

        if (in_array($currentEventStatus, [
            WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON,
            WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON_REPEAT,
            WorkflowTypeStatus::STATUS_FETCH_TEMPLATE,
            WorkflowTypeStatus::STATUS_FETCH_TEMPLATE_REPEAT,
            WorkflowTypeStatus::STATUS_REDIRECT,
            WorkflowTypeStatus::STATUS_REDIRECT_REPEAT,
            WorkflowTypeStatus::STATUS_WORKFLOW_RESET,
        ], true)) {
            if ($workflowEvent !== null) {
                $activationDate = $process->activationDate;
                if ($activationDate === 0) {
                    $runCurrentEvent = !in_array($currentEventStatus, [
                        WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON,
                        WorkflowTypeStatus::STATUS_FETCH_TEMPLATE,
                        WorkflowTypeStatus::STATUS_REDIRECT,
                    ], true);
                } elseif (time() < $activationDate) {
                    $done = true;
                } else {
                    $runCurrentEvent = !in_array($currentEventStatus, [
                        WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON,
                        WorkflowTypeStatus::STATUS_FETCH_TEMPLATE,
                        WorkflowTypeStatus::STATUS_REDIRECT,
                    ], true);
                }
            }
        }

        while (!$done) {
            if ($runCurrentEvent && $workflowEvent instanceof WorkflowEvent) {
                $eventType = $this->eventTypeRegistry->get($workflowEvent->workflowTypeString);
                if ($eventType === null) {
                    $this->logger?->warning(
                        'Legacy workflow event type is not registered; skipping event. Clear Symfony cache after enabling extension bundles.',
                        [
                            'event_type' => $workflowEvent->workflowTypeString,
                            'workflow_id' => $workflow->id,
                            'process_id' => $process->id,
                        ]
                    );
                } else {
                    [$moduleName, $functionName, $connectType] = $this->resolveTriggerContext($process);
                    if (!$eventType->isAllowed($moduleName, $functionName, $connectType)) {
                        $currentEventStatus = WorkflowTypeStatus::STATUS_ACCEPTED;
                    } else {
                        $currentEventStatus = $eventType->execute($process, $workflowEvent);
                    }
                    $process->eventStatus = $currentEventStatus;

                    $workflowStatus = $this->mapEventStatusToWorkflowStatus(
                        $currentEventStatus,
                        $process,
                        $done
                    );

                    if ($currentEventStatus === WorkflowTypeStatus::STATUS_REDIRECT
                        || $currentEventStatus === WorkflowTypeStatus::STATUS_REDIRECT_REPEAT
                    ) {
                        $process->advance();
                    }
                }
            }

            $runCurrentEvent = true;

            if (!$done) {
                $nextEventPos = $process->eventPosition + 1;
                $nextEventId = $this->storage->findEventIdByPlacement($workflow->id, $nextEventPos);

                if ($nextEventId !== null) {
                    $process->advance($nextEventId, $nextEventPos, $currentEventStatus);
                    $workflowEvent = $this->storage->findWorkflowEvent($nextEventId);
                } else {
                    $done = true;
                    $workflowEvent = null;
                    $workflowStatus = WorkflowStatus::STATUS_DONE;
                    $process->advance();
                }
            }
        }

        $process->status = $workflowStatus;
        $process->modified = time();

        return $workflowStatus;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function resolveTriggerContext(WorkflowProcess $process): array
    {
        $parameters = $process->getParameterList();

        return [
            (string) ($parameters['module_name'] ?? 'content'),
            (string) ($parameters['module_function'] ?? 'publish'),
            (string) ($parameters['connect_type'] ?? (
                ($parameters['trigger_name'] ?? '') === 'post_publish' ? 'after' : 'before'
            )),
        ];
    }

    private function mapEventStatusToWorkflowStatus(
        int $currentEventStatus,
        WorkflowProcess $process,
        bool &$done,
    ): int {
        switch ($currentEventStatus) {
            case WorkflowTypeStatus::STATUS_ACCEPTED:
                return WorkflowStatus::STATUS_DONE;
            case WorkflowTypeStatus::STATUS_WORKFLOW_DONE:
                $done = true;

                return WorkflowStatus::STATUS_DONE;
            case WorkflowTypeStatus::STATUS_REJECTED:
                $done = true;

                return WorkflowStatus::STATUS_FAILED;
            case WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON:
            case WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON_REPEAT:
                $done = true;

                return WorkflowStatus::STATUS_DEFERRED_TO_CRON;
            case WorkflowTypeStatus::STATUS_FETCH_TEMPLATE:
                $done = true;

                return WorkflowStatus::STATUS_FETCH_TEMPLATE;
            case WorkflowTypeStatus::STATUS_FETCH_TEMPLATE_REPEAT:
                $done = true;

                return WorkflowStatus::STATUS_FETCH_TEMPLATE_REPEAT;
            case WorkflowTypeStatus::STATUS_REDIRECT:
            case WorkflowTypeStatus::STATUS_REDIRECT_REPEAT:
                $done = true;

                return WorkflowStatus::STATUS_REDIRECT;
            case WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED:
                $done = true;
                $process->advance();

                return WorkflowStatus::STATUS_CANCELLED;
            case WorkflowTypeStatus::STATUS_WORKFLOW_RESET:
                $done = true;
                $process->reset();

                return WorkflowStatus::STATUS_RESET;
            case WorkflowTypeStatus::STATUS_RUN_SUB_EVENT:
                $done = true;

                return WorkflowStatus::STATUS_BUSY;
            default:
                return WorkflowStatus::STATUS_BUSY;
        }
    }
}