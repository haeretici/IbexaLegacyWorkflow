<?php

declare(strict_types=1);

namespace Haeretici\OnUpdateObjectStateAfterWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\AbstractWorkflowEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;
use Haeretici\OnUpdateObjectStateAfterWorkflowBundle\Workflow\Service\OnUpdateObjectStateAfterEventTypeLogger;

class OnUpdateObjectStateAfterEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_haeretici_onupdateobjectstateafter';

    public function __construct(
        private readonly OnUpdateObjectStateAfterEventTypeLogger $logger,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'On update object state after',
            ['content' => ['updateobjectstate' => ['after']]]
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
            'Post-updateobjectstate hook ran for content %d version %d',
            (int) $parameters['object_id'],
            (int) $parameters['version']
        ));

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }
}
