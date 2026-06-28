<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;

/**
 * Maps Ibexa TrashService events to legacy content_delete vs content_removelocation.
 *
 * Admin "remove location" trashes a secondary assignment; "move to trash" trashes the main tree location.
 * Classification uses main-location identity only so before/after trash events stay consistent.
 */
final class TrashWorkflowOperationResolver
{
    public const OPERATION_DELETE = 'content_delete';
    public const OPERATION_REMOVE_LOCATION = 'content_removelocation';

    public function resolveOperation(Location $location): string
    {
        $mainLocationId = $location->getContentInfo()->getMainLocationId();
        if ($mainLocationId !== null && $location->getId() !== $mainLocationId) {
            return self::OPERATION_REMOVE_LOCATION;
        }

        return self::OPERATION_DELETE;
    }
}