<?php

declare(strict_types=1);

namespace Haeretici\OnUpdatePriorityAfterWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\AbstractWorkflowEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;
use Haeretici\OnUpdatePriorityAfterWorkflowBundle\Workflow\Service\OnUpdatePriorityAfterEventTypeLogger;

class OnUpdatePriorityAfterEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_haeretici_onupdatepriorityafter';

    public function __construct(
        private readonly OnUpdatePriorityAfterEventTypeLogger $logger,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'On update priority after',
            ['content' => ['updatepriority' => ['after']]]
        );
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        $parameters = $process->getParameterList();
        $this->logger->logTriggered($parameters);

        if (!isset($parameters['object_id'], $parameters['version'])) {
            return WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED;
        }

        $this->setInformation(sprintf(
            'Post-updatepriority hook ran for content %d version %d',
            (int) $parameters['object_id'],
            (int) $parameters['version']
        ));

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }
}
