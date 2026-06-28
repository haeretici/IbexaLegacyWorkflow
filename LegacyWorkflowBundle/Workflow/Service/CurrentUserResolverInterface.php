<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

interface CurrentUserResolverInterface
{
    public function getCurrentUserId(): int;
}