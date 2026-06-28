<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Model;

class Trigger
{
    public function __construct(
        public ?int $id,
        public string $moduleName,
        public string $functionName,
        public string $connectType,
        public int $workflowId,
        public string $name,
    ) {
    }
}