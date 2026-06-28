<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowTypeStatus;

abstract class AbstractWorkflowEventType implements WorkflowEventTypeInterface
{
    protected string $information = '';
    protected int $activationDate = 0;

    public function __construct(
        protected readonly string $typeString,
        protected readonly string $name,
        /** @var array<string, array<string, array<int, string>>|true> */
        protected readonly array $allowedTriggers = ['*' => true],
    ) {
    }

    public function getTypeString(): string
    {
        return $this->typeString;
    }

    public function getAllowedTriggers(): array
    {
        return $this->allowedTriggers;
    }

    public function isAllowed(string $moduleName, string $functionName, string $connectType): bool
    {
        if (isset($this->allowedTriggers['*'])) {
            return true;
        }

        if (!isset($this->allowedTriggers[$moduleName][$functionName])) {
            return false;
        }

        return in_array($connectType, $this->allowedTriggers[$moduleName][$functionName], true);
    }

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int
    {
        return WorkflowTypeStatus::STATUS_NONE;
    }

    public function getInformation(): string
    {
        return $this->information;
    }

    public function getActivationDate(): int
    {
        return $this->activationDate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function setInformation(string $information): void
    {
        $this->information = $information;
    }

    protected function setActivationDate(int $activationDate): void
    {
        $this->activationDate = $activationDate;
    }
}