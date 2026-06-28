<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Model;

use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowStatus;

class WorkflowProcess
{
    public ?int $id = null;
    public string $processKey = '';
    public int $workflowId = 0;
    public int $userId = 0;
    public int $contentId = 0;
    public int $contentVersion = 0;
    public int $nodeId = 0;
    public int $eventId = 0;
    public int $eventPosition = 0;
    public int $eventState = 0;
    public int $lastEventId = 0;
    public int $lastEventPosition = 0;
    public int $lastEventStatus = 0;
    public int $eventStatus = 0;
    public int $activationDate = 0;
    public int $status = WorkflowStatus::STATUS_NONE;
    public int $created = 0;
    public int $modified = 0;

    /** @var array<string, mixed> */
    public array $parameters = [];

    /** @var array{templateName?: string, templateVars?: array<string, mixed>, path?: array<int, array<string, mixed>>}|null */
    public ?array $template = null;

    public ?string $redirectUrl = null;

    public function reset(): void
    {
        $this->eventId = 0;
        $this->eventPosition = 0;
        $this->lastEventId = 0;
        $this->lastEventPosition = 0;
        $this->lastEventStatus = 0;
        $this->activationDate = 0;
        $this->eventStatus = 0;
        $this->eventState = 0;
    }

    public function advance(int $nextEventId = 0, int $nextEventPos = 0, int $status = 0): void
    {
        $this->lastEventId = $this->eventId;
        $this->lastEventPosition = $this->eventPosition;
        $this->lastEventStatus = $status;
        $this->eventId = $nextEventId;
        $this->eventPosition = $nextEventPos;
        $this->activationDate = 0;
    }

    /** @return array<string, mixed> */
    public function getParameterList(): array
    {
        return $this->parameters;
    }
}