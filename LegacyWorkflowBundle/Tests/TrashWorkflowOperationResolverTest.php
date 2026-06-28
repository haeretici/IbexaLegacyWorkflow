<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\Service\TrashWorkflowOperationResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use PHPUnit\Framework\TestCase;

class TrashWorkflowOperationResolverTest extends TestCase
{
    public function testResolvesSecondaryLocationTrashToRemoveLocation(): void
    {
        $resolver = new TrashWorkflowOperationResolver();
        $location = $this->createLocation(99, 42, 10);

        $this->assertSame(
            TrashWorkflowOperationResolver::OPERATION_REMOVE_LOCATION,
            $resolver->resolveOperation($location)
        );
    }

    public function testResolvesMainLocationTrashToDelete(): void
    {
        $resolver = new TrashWorkflowOperationResolver();
        $location = $this->createLocation(10, 42, 10);

        $this->assertSame(
            TrashWorkflowOperationResolver::OPERATION_DELETE,
            $resolver->resolveOperation($location)
        );
    }

    private function createLocation(int $locationId, int $contentId, int $mainLocationId): Location
    {
        $contentInfo = new ContentInfo([
            'id' => $contentId,
            'currentVersionNo' => 1,
            'mainLocationId' => $mainLocationId,
            'contentTypeId' => 1,
            'name' => 'Test',
            'sectionId' => 1,
            'published' => true,
            'ownerId' => 14,
            'mainLanguageCode' => 'eng-GB',
            'remoteId' => 'remote-' . $contentId,
            'status' => ContentInfo::STATUS_PUBLISHED,
        ]);

        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn($locationId);
        $location->method('getContentInfo')->willReturn($contentInfo);

        return $location;
    }
}