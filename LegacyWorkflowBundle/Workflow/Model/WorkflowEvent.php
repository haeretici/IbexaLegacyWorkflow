<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Model;

class WorkflowEvent
{
    public function __construct(
        public int $id,
        public int $workflowId,
        public string $workflowTypeString,
        public int $version = 0,
        public string $description = '',
        public int $placement = 0,
        public int $dataInt1 = 0,
        public int $dataInt2 = 0,
        public int $dataInt3 = 0,
        public int $dataInt4 = 0,
        public string $dataText1 = '',
        public string $dataText2 = '',
        public string $dataText3 = '',
        public string $dataText4 = '',
        public string $dataText5 = '',
    ) {
    }
}