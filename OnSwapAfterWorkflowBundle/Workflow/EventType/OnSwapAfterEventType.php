<?php

declare(strict_types=1);

namespace Haeretici\OnSwapAfterWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\AbstractWorkflowEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;
use Haeretici\OnSwapAfterWorkflowBundle\Workflow\Service\OnSwapAfterEventTypeLogger;

class OnSwapAfterEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_haeretici_onswapafter';

    public function __construct(
        private readonly OnSwapAfterEventTypeLogger $logger,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'On swap after',
            ['content' => ['swap' => ['after']]]
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
            'Post-swap hook ran for content %d version %d',
            (int) $parameters['object_id'],
            (int) $parameters['version']
        ));

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }
}
