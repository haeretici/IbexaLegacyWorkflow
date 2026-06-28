<?php

declare(strict_types=1);

namespace Haeretici\OneForAllBeforeWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\AbstractWorkflowEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;
use Haeretici\OneForAllBeforeWorkflowBundle\Workflow\Service\OneForAllBeforeEventTypeLogger;

class OnDeleteBeforeEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_haeretici_ondeletebefore';

    public function __construct(
        private readonly OneForAllBeforeEventTypeLogger $logger,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'On delete before',
            ['content' => ['delete' => ['before']]]
        );
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        $parameters = $process->getParameterList();
        $this->logger->logTriggered(self::TYPE_STRING, $parameters);

        if (!isset($parameters['object_id'], $parameters['version'])) {
            return WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED;
        }

        $this->setInformation(sprintf(
            'Pre-delete hook ran for content %d version %d',
            (int) $parameters['object_id'],
            (int) $parameters['version']
        ));

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }
}
