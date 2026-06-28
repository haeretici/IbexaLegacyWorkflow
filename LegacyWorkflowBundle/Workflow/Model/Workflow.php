<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Model;

class Workflow
{
    public function __construct(
        public int $id,
        public int $version = 0,
        public bool $isEnabled = true,
        public string $workflowTypeString = 'group_ezserial',
        public string $name = '',
    ) {
    }
}