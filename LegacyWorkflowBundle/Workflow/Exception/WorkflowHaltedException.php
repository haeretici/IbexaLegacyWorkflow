<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Exception;

class WorkflowHaltedException extends \RuntimeException
{
    /** @param array{Status: int, Result: mixed, WorkflowProcess?: mixed} $triggerResult */
    public function __construct(
        private readonly array $triggerResult,
        string $message = 'Workflow halted content publish operation',
    ) {
        parent::__construct($message);
    }

    /** @return array{Status: int, Result: mixed, WorkflowProcess?: mixed} */
    public function getTriggerResult(): array
    {
        return $this->triggerResult;
    }
}