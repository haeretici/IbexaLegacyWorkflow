<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\EventSubscriber\ContentOperationsWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TrashWorkflowOperationResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Exception\WorkflowHaltedException;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeHideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\HideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\UnhideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Section\AssignSectionEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\TrashEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\Section;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use PHPUnit\Framework\TestCase;

class ContentOperationsWorkflowSubscriberTest extends TestCase
{
    public function testSubscriberDeclaresAllOperationEventWiring(): void
    {
        $events = ContentOperationsWorkflowSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(BeforeHideLocationEvent::class, $events);
        $this->assertArrayHasKey(HideLocationEvent::class, $events);
        $this->assertArrayHasKey(UnhideLocationEvent::class, $events);
        $this->assertArrayHasKey(AssignSectionEvent::class, $events);
        $this->assertSame('content_hide', ContentOperationsWorkflowSubscriber::OPERATION_HIDE);
        $this->assertSame('content_show', ContentOperationsWorkflowSubscriber::OPERATION_SHOW);
        $this->assertSame('content_updatesection', ContentOperationsWorkflowSubscriber::OPERATION_UPDATE_SECTION);
    }

    public function testOnBeforeHideLocationRunsPreHideTriggerWithNodeId(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                'pre_hide',
                'content',
                'hide',
                $this->callback(static function (array $parameters): bool {
                    return $parameters['object_id'] === 42
                        && $parameters['version'] === 3
                        && $parameters['node_id'] === 99
                        && $parameters['user_id'] === 14
                        && $parameters['operation'] === 'content_hide';
                }),
                ['node_id']
            )
            ->willReturn([
                'Status' => TriggerStatus::FETCH_TEMPLATE_REPEAT,
                'Result' => ['content' => 'approval needed'],
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);

        $this->expectException(WorkflowHaltedException::class);
        $subscriber->onBeforeHideLocation(new BeforeHideLocationEvent($this->createLocation(99, 42, 3)));
    }

    public function testOnHideLocationRunsPostHideTriggerWithoutHalting(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with('post_hide', 'content', 'hide', $this->anything(), ['node_id'])
            ->willReturn([
                'Status' => TriggerStatus::FETCH_TEMPLATE_REPEAT,
                'Result' => ['content' => 'post template'],
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $location = $this->createLocation(12, 7, 1);
        $subscriber->onHideLocation(new HideLocationEvent($location, $location));

        $this->assertSame(TriggerStatus::FETCH_TEMPLATE_REPEAT, $subscriber->getLastResult()['Status']);
    }

    public function testOnTrashLocationRunsPostRemoveLocationTrigger(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                'post_removelocation',
                'content',
                'removelocation',
                $this->callback(static function (array $parameters): bool {
                    return $parameters['object_id'] === 42
                        && $parameters['node_id'] === 99
                        && $parameters['operation'] === 'content_removelocation';
                }),
                ['node_id']
            )
            ->willReturn([
                'Status' => TriggerStatus::WORKFLOW_DONE,
                'Result' => null,
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $location = $this->createLocation(99, 42, 3, 10);
        $subscriber->onTrashLocation(new TrashEvent(null, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testOnTrashLocationRunsPostDeleteTriggerForMainLocation(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                'post_delete',
                'content',
                'delete',
                $this->callback(static function (array $parameters): bool {
                    return $parameters['object_id'] === 42
                        && $parameters['node_id'] === 10
                        && $parameters['move_to_trash'] === 1
                        && $parameters['node_id_list'] === [10]
                        && $parameters['operation'] === 'content_delete';
                }),
                ['object_id']
            )
            ->willReturn([
                'Status' => TriggerStatus::WORKFLOW_DONE,
                'Result' => null,
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $location = $this->createLocation(10, 42, 3, 10);
        $subscriber->onTrashLocation(new TrashEvent(null, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testOnUnhideLocationRunsPostShowTriggerWithoutHalting(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                'post_show',
                'content',
                'show',
                $this->callback(static function (array $parameters): bool {
                    return $parameters['object_id'] === 42
                        && $parameters['node_id'] === 99
                        && $parameters['operation'] === 'content_show';
                }),
                ['node_id']
            )
            ->willReturn([
                'Status' => TriggerStatus::WORKFLOW_DONE,
                'Result' => null,
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $location = $this->createLocation(99, 42, 3);
        $subscriber->onUnhideLocation(new UnhideLocationEvent($location, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testOnAssignSectionRunsPostUpdateSectionTrigger(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                'post_updatesection',
                'content',
                'updatesection',
                $this->callback(static function (array $parameters): bool {
                    return $parameters['object_id'] === 5
                        && $parameters['selected_section_id'] === 8
                        && $parameters['operation'] === 'content_updatesection';
                }),
                ['object_id', 'selected_section_id']
            )
            ->willReturn([
                'Status' => TriggerStatus::WORKFLOW_DONE,
                'Result' => null,
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $contentInfo = $this->createContentInfo(5, 2, 10);
        $section = new Section(['id' => 8, 'identifier' => 'standard', 'name' => 'Standard']);
        $subscriber->onAssignSection(new AssignSectionEvent($contentInfo, $section));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    private function createSubscriber(TriggerRunner $triggerRunner): ContentOperationsWorkflowSubscriber
    {
        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        return new ContentOperationsWorkflowSubscriber(
            $triggerRunner,
            $permissionResolver,
            new TrashWorkflowOperationResolver(),
            true,
        );
    }

    private function createLocation(int $locationId, int $contentId, int $version, ?int $mainLocationId = null): Location
    {
        $contentInfo = $this->createContentInfo($contentId, $version, $mainLocationId ?? $locationId);
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn($locationId);
        $location->method('getContentId')->willReturn($contentId);
        $location->method('getContentInfo')->willReturn($contentInfo);

        return $location;
    }

    private function createContentInfo(int $contentId, int $version, int $mainLocationId): ContentInfo
    {
        return new ContentInfo([
            'id' => $contentId,
            'currentVersionNo' => $version,
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
    }
}