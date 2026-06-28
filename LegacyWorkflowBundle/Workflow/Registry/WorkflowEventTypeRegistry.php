<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Registry;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\WorkflowEventTypeInterface;

class WorkflowEventTypeRegistry
{
    /** @var array<string, WorkflowEventTypeInterface> */
    private array $types = [];

    public function register(WorkflowEventTypeInterface $type): void
    {
        $this->types[$type->getTypeString()] = $type;
    }

    public function has(string $typeString): bool
    {
        return isset($this->types[$typeString]);
    }

    public function get(string $typeString): ?WorkflowEventTypeInterface
    {
        return $this->types[$typeString] ?? null;
    }

    /** @return string[] */
    public function getRegisteredTypeStrings(): array
    {
        return array_keys($this->types);
    }
}