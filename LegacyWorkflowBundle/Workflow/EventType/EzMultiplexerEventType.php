<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\ContentContextInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\SubWorkflowExecutor;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;

class EzMultiplexerEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_ezmultiplexer';

    public function __construct(
        private readonly ContentContextInterface $contentContext,
        private readonly SubWorkflowExecutor $subWorkflowExecutor,
    ) {
        parent::__construct(
            self::TYPE_STRING,
            'Multiplexer',
            ['content' => ['publish' => ['before', 'after']]]
        );
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        $parameters = $process->getParameterList();
        $objectId = (int) ($parameters['object_id'] ?? 0);
        $selectedWorkflowId = $event->dataInt1;

        if ($objectId <= 0 || $selectedWorkflowId <= 0) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        $sections = $this->decodeList($event->dataText1, defaultAll: true);
        $classes = $this->decodeList($event->dataText5, defaultAll: true);

        $sectionId = $this->contentContext->getSectionId($objectId);
        $classId = $this->contentContext->getContentClassId($objectId);

        if (!$this->matchesFilter($sections, (string) $sectionId)
            || !$this->matchesFilter($classes, (string) $classId)
        ) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        $childParameters = array_merge($parameters, [
            'workflow_id' => $selectedWorkflowId,
            'user_id' => (int) ($parameters['user_id'] ?? 0),
            'parent_process_id' => $process->id ?? 0,
        ]);

        return $this->subWorkflowExecutor->execute($process, $selectedWorkflowId, $childParameters);
    }

    /** @return string[] */
    private function decodeList(string $value, bool $defaultAll = false): array
    {
        $value = trim($value);
        if ($value === '') {
            return $defaultAll ? ['-1'] : [];
        }

        return array_filter(explode(',', $value), static fn (string $item): bool => $item !== '');
    }

    /** @param string[] $allowed */
    private function matchesFilter(array $allowed, string $value): bool
    {
        if (in_array('-1', $allowed, true)) {
            return true;
        }

        return in_array($value, $allowed, true);
    }
}