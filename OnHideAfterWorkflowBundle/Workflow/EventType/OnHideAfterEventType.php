<?php

declare(strict_types=1);

namespace Haeretici\OnHideAfterWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\AbstractWorkflowEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;
use Haeretici\OnHideAfterWorkflowBundle\Workflow\Service\OnHideAfterEventTypeLogger;

class OnHideAfterEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_haeretici_onhideafter';

    public function __construct(
        private readonly OnHideAfterEventTypeLogger $logger,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'On hide after',
            ['content' => ['hide' => ['after']]]
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
            'Post-hide hook ran for content %d version %d',
            (int) $parameters['object_id'],
            (int) $parameters['version']
        ));

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }
}
