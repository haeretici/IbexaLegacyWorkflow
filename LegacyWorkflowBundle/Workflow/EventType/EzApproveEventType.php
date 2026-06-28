<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;

class EzApproveEventType extends AbstractWorkflowEventType
{
    public const TYPE_STRING = 'event_ezapprove';

    public function __construct()
    {
        parent::__construct(
            self::TYPE_STRING,
            'Approve',
            ['content' => ['publish' => ['before']]]
        );
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        $parameters = $process->getParameterList();
        if (!isset($parameters['object_id'], $parameters['version'])) {
            return WorkflowTypeStatus::STATUS_WORKFLOW_CANCELLED;
        }

        $approveUsers = $this->decodeList($event->dataText3);
        $userId = (int) ($parameters['user_id'] ?? 0);

        if ($approveUsers === [] || in_array((string) $userId, $approveUsers, true)) {
            return WorkflowTypeStatus::STATUS_ACCEPTED;
        }

        $this->setInformation('Content requires approval');
        $process->template = [
            'templateName' => 'workflow/approve.tpl',
            'templateVars' => [
                'object_id' => $parameters['object_id'],
                'version' => $parameters['version'],
            ],
            'path' => [['text' => 'Approve', 'url' => false]],
        ];

        return WorkflowTypeStatus::STATUS_FETCH_TEMPLATE_REPEAT;
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