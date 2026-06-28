<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\EventSubscriber\ContentOperationsWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TrashWorkflowOperationResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Haeretici\OnHideAfterWorkflowBundle\Workflow\EventType\OnHideAfterEventType;
use Haeretici\OnDeleteAfterWorkflowBundle\Workflow\EventType\OnDeleteAfterEventType;
use Haeretici\OnRemoveLocationAfterWorkflowBundle\Workflow\EventType\OnRemoveLocationAfterEventType;
use Haeretici\OnShowAfterWorkflowBundle\Workflow\EventType\OnShowAfterEventType;
use Ibexa\Contracts\Core\Repository\Events\Location\HideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\TrashEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\UnhideLocationEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use PHPUnit\Framework\TestCase;

class ContentOperationsWorkflowSubscriberIntegrationTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-content-ops-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testSubscriberExecutesAdminConfiguredHideAfterWorkflow(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(TestSupport::onHideAfterEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Hide after path');
        $event = $adminService->addWorkflowEvent($workflow->id, OnHideAfterEventType::TYPE_STRING, 'after');
        $this->assertNotNull($event);
        $adminService->assignTrigger('content_hide', 'after', $workflow->id);

        $triggerRunner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        $subscriber = $this->createSubscriber($triggerRunner, $permissionResolver);
        $location = $this->createLocation(88, 55, 2);
        $subscriber->onHideLocation(new HideLocationEvent($location, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testSubscriberExecutesAdminConfiguredShowAfterWorkflow(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(TestSupport::onShowAfterEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Show after path');
        $event = $adminService->addWorkflowEvent($workflow->id, OnShowAfterEventType::TYPE_STRING, 'after');
        $this->assertNotNull($event);
        $adminService->assignTrigger('content_show', 'after', $workflow->id);

        $triggerRunner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        $subscriber = $this->createSubscriber($triggerRunner, $permissionResolver);
        $location = $this->createLocation(91, 56, 2);
        $subscriber->onUnhideLocation(new UnhideLocationEvent($location, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testSubscriberExecutesAdminConfiguredRemoveLocationAfterWorkflowViaTrashEvent(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(TestSupport::onRemoveLocationAfterEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Remove location after path');
        $event = $adminService->addWorkflowEvent($workflow->id, OnRemoveLocationAfterEventType::TYPE_STRING, 'after');
        $this->assertNotNull($event);
        $adminService->assignTrigger('content_removelocation', 'after', $workflow->id);

        $triggerRunner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        $subscriber = $this->createSubscriber($triggerRunner, $permissionResolver);
        $location = $this->createLocation(77, 33, 2, 10);
        $subscriber->onTrashLocation(new TrashEvent(null, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testSubscriberExecutesAdminConfiguredDeleteAfterWorkflowViaTrashEvent(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(TestSupport::onDeleteAfterEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Delete after path');
        $event = $adminService->addWorkflowEvent($workflow->id, OnDeleteAfterEventType::TYPE_STRING, 'after');
        $this->assertNotNull($event);
        $adminService->assignTrigger('content_delete', 'after', $workflow->id);

        $triggerRunner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        $subscriber = $this->createSubscriber($triggerRunner, $permissionResolver);
        $location = $this->createLocation(60, 44, 2, 60);
        $subscriber->onTrashLocation(new TrashEvent(null, $location));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testAdminAssignsHideTriggerWithFunctionBasedName(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $adminService = new WorkflowAdminService(
            $storage,
            new WorkflowEventTypeRegistry(),
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Hide workflow');
        $adminService->assignTrigger('content_hide', 'before', $workflow->id);

        $reloaded = new YamlWorkflowStorage($this->storagePath);
        $trigger = $reloaded->findTrigger('pre_hide', 'content', 'hide');

        $this->assertNotNull($trigger);
        $this->assertSame($workflow->id, $trigger->workflowId);
    }

    private function createSubscriber(
        TriggerRunner $triggerRunner,
        PermissionResolver $permissionResolver,
    ): ContentOperationsWorkflowSubscriber {
        return new ContentOperationsWorkflowSubscriber(
            $triggerRunner,
            $permissionResolver,
            new TrashWorkflowOperationResolver(),
            true,
        );
    }

    private function createLocation(int $locationId, int $contentId, int $version, ?int $mainLocationId = null): Location
    {
        $contentInfo = new ContentInfo([
            'id' => $contentId,
            'currentVersionNo' => $version,
            'mainLocationId' => $mainLocationId ?? $locationId,
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
        $location->method('getContentId')->willReturn($contentId);
        $location->method('getContentInfo')->willReturn($contentInfo);

        return $location;
    }
}