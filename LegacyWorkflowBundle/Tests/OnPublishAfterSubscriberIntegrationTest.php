<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\EventSubscriber\PublishWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use Ibexa\Contracts\Core\Repository\Events\Content\PublishVersionEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use PHPUnit\Framework\TestCase;

class OnPublishAfterSubscriberIntegrationTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-onpublishafter-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testPostPublishSubscriberExecutesOnPublishAfterEventType(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $eventType = TestSupport::onPublishAfterEventType();
        $registry->register($eventType);
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Standard');
        $event = $adminService->addWorkflowEvent($workflow->id, OnPublishAfterEventType::TYPE_STRING);
        $this->assertNotNull($event);
        $adminService->assignTrigger('content_publish', 'after', $workflow->id);

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
        $subscriber->onPublishVersion($this->createPublishEvent(77, 3));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
        $this->assertStringContainsString(
            'Post-publish hook ran for content 77 version 3',
            $eventType->getInformation()
        );
    }

    private function createPublishEvent(int $contentId, int $versionNo): PublishVersionEvent
    {
        $contentInfo = $this->createMock(ContentInfo::class);
        $contentInfo->method('getId')->willReturn($contentId);
        $versionInfo = $this->createMock(VersionInfo::class);
        $versionInfo->method('getVersionNo')->willReturn($versionNo);
        $versionInfo->method('getContentInfo')->willReturn($contentInfo);
        $content = $this->createMock(Content::class);
        $content->method('getId')->willReturn($contentId);
        $content->method('getVersionInfo')->willReturn($versionInfo);

        return new PublishVersionEvent($content, $versionInfo, ['eng-GB']);
    }
}