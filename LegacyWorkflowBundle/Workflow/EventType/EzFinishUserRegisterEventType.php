<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\ContentContextInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;

class EzFinishUserRegisterEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_ezfinishuserregister';

    public function __construct(
        private readonly ContentContextInterface $contentContext,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'Finish User Registration',
            ['content' => ['publish' => ['after']]]
        );
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        $parameters = $process->getParameterList();
        $objectId = (int) ($parameters['object_id'] ?? 0);

        if ($objectId <= 0) {
            return WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED;
        }

        if (!$this->contentContext->isUserContent($objectId)) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        $this->contentContext->finishUserRegistration($objectId);

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }
}