<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\ContentContextInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;

class EzWaitUntilDateEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_ezwaituntildate';

    public function __construct(
        private readonly ContentContextInterface $contentContext,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'Wait until date',
            ['content' => ['publish' => ['before', 'after']]]
        );
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        $parameters = $process->getParameterList();
        $objectId = (int) ($parameters['object_id'] ?? 0);
        $version = (int) ($parameters['version'] ?? 0);

        if ($objectId <= 0 || $version <= 0) {
            return WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED;
        }

        $attributeIds = $this->decodeList($event->dataText1);
        $timestamp = $this->contentContext->getEarliestDateAttributeTimestamp($objectId, $version, $attributeIds);

        if ($timestamp === null) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        if (time() < $timestamp) {
            $this->setInformation('Event delayed until ' . date('Y-m-d H:i:s', $timestamp));
            $this->setActivationDate($timestamp);

            return WorkflowTypeStatus::STATUS_DEFERRED_TO_CRON_REPEAT;
        }

        if ($event->dataInt1 !== 0) {
            $this->contentContext->updatePublishedDate($objectId, $timestamp);
        }

        return WorkflowTypeStatus::STATUS_ACCEPTED;
    }

    /** @return string[] */
    private function decodeList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return array_filter(explode(',', $value), static fn (string $item): bool => $item !== '');
    }
}