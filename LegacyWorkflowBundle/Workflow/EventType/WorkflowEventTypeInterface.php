<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\EventType;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;

interface WorkflowEventTypeInterface
{
    public function getTypeString(): string;

    /** @return array<string, array<string, array<int, string>>|true> */
    public function getAllowedTriggers(): array;

    public function isAllowed(string $moduleName, string $functionName, string $connectType): bool;

    public function execute(WorkflowProcess $process, WorkflowEvent $event): int;

    public function getInformation(): string;

    public function getActivationDate(): int;

    public function getName(): string;
}