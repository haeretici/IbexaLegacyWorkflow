<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\EventSubscriber\PublishWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowIniInspector;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Exception\WorkflowHaltedException;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Ibexa\Contracts\Core\Repository\Events\Content\BeforePublishVersionEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use PHPUnit\Framework\TestCase;

class PublishWorkflowSubscriberIntegrationTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-subscriber-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testSubscriberExecutesAdminConfiguredWorkflowFromYamlStorage(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Subscriber path');
        $event = $adminService->addWorkflowEvent($workflow->id, EzApproveEventType::TYPE_STRING);
        $this->assertNotNull($event);
        $adminService->updateWorkflowEventData($workflow->id, $event->id, ['dataText3' => '999']);
        $adminService->assignTrigger('content_publish', 'before', $workflow->id);

        $triggerRunner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        $subscriber = new PublishWorkflowSubscriber($triggerRunner, $permissionResolver, true);

        $this->expectException(WorkflowHaltedException::class);
        $subscriber->onBeforePublishVersion($this->createBeforePublishEvent(55, 2));

        $this->assertSame(TriggerStatus::FETCH_TEMPLATE_REPEAT, $subscriber->getLastResult()['Status']);
    }

    private function createBeforePublishEvent(int $contentId, int $versionNo): BeforePublishVersionEvent
    {
        $contentInfo = $this->createMock(ContentInfo::class);
        $contentInfo->method('getId')->willReturn($contentId);
        $versionInfo = $this->createMock(VersionInfo::class);
        $versionInfo->method('getVersionNo')->willReturn($versionNo);
        $versionInfo->method('getContentInfo')->willReturn($contentInfo);

        return new BeforePublishVersionEvent($versionInfo, ['eng-GB']);
    }
}