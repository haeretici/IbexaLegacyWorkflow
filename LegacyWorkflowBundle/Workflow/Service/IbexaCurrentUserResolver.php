<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Ibexa\Contracts\Core\Repository\PermissionResolver;

class IbexaCurrentUserResolver implements CurrentUserResolverInterface
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    public function getCurrentUserId(): int
    {
        return $this->permissionResolver->getCurrentUserReference()->getUserId();
    }
}