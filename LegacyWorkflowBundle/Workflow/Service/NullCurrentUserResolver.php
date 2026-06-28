<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

class NullCurrentUserResolver implements CurrentUserResolverInterface
{
    public function getCurrentUserId(): int
    {
        return 0;
    }
}