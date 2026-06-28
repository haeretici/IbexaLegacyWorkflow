<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\EventSubscriber\PublishWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\Workflow\Exception\WorkflowHaltedException;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Ibexa\Contracts\Core\Repository\Events\Content\BeforePublishVersionEvent;
use Ibexa\Contracts\Core\Repository\Events\Content\PublishVersionEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference;
use PHPUnit\Framework\TestCase;

class PublishWorkflowSubscriberTest extends TestCase
{
    public function testSubscriberDeclaresPublishEventWiring(): void
    {
        $events = PublishWorkflowSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(BeforePublishVersionEvent::class, $events);
        $this->assertArrayHasKey(PublishVersionEvent::class, $events);
        $this->assertSame('content_publish', PublishWorkflowSubscriber::OPERATION_CONTENT_PUBLISH);
    }

    public function testOnBeforePublishVersionStoresResultAndHaltsOnFetchTemplate(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                PublishWorkflowSubscriber::TRIGGER_PRE_PUBLISH,
                PublishWorkflowSubscriber::MODULE_CONTENT,
                PublishWorkflowSubscriber::FUNCTION_PUBLISH,
                $this->callback(static function (array $parameters): bool {
                    return $parameters['object_id'] === 55
                        && $parameters['version'] === 2
                        && $parameters['user_id'] === 14
                        && $parameters['operation'] === PublishWorkflowSubscriber::OPERATION_CONTENT_PUBLISH;
                }),
                ['object_id', 'version']
            )
            ->willReturn([
                'Status' => TriggerStatus::FETCH_TEMPLATE_REPEAT,
                'Result' => ['content' => 'approval needed'],
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);

        $this->expectException(WorkflowHaltedException::class);
        $subscriber->onBeforePublishVersion($this->createBeforePublishEvent(55, 2));
    }

    public function testOnBeforePublishVersionAllowsWorkflowDone(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->method('runTrigger')->willReturn([
            'Status' => TriggerStatus::WORKFLOW_DONE,
            'Result' => null,
        ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $subscriber->onBeforePublishVersion($this->createBeforePublishEvent(10, 1));

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $subscriber->getLastResult()['Status']);
    }

    public function testOnPublishVersionStoresResultWithoutHalting(): void
    {
        $triggerRunner = $this->createMock(TriggerRunner::class);
        $triggerRunner->expects($this->once())
            ->method('runTrigger')
            ->with(
                PublishWorkflowSubscriber::TRIGGER_POST_PUBLISH,
                PublishWorkflowSubscriber::MODULE_CONTENT,
                PublishWorkflowSubscriber::FUNCTION_PUBLISH,
                $this->anything(),
                ['object_id', 'version']
            )
            ->willReturn([
                'Status' => TriggerStatus::FETCH_TEMPLATE_REPEAT,
                'Result' => ['content' => 'post template'],
            ]);

        $subscriber = $this->createSubscriber($triggerRunner);
        $subscriber->onPublishVersion($this->createPublishEvent(77, 3));

        $this->assertSame(TriggerStatus::FETCH_TEMPLATE_REPEAT, $subscriber->getLastResult()['Status']);
    }

    private function createSubscriber(TriggerRunner $triggerRunner): PublishWorkflowSubscriber
    {
        $permissionResolver = $this->createMock(PermissionResolver::class);
        $userReference = $this->createMock(UserReference::class);
        $userReference->method('getUserId')->willReturn(14);
        $permissionResolver->method('getCurrentUserReference')->willReturn($userReference);

        return new PublishWorkflowSubscriber($triggerRunner, $permissionResolver, true);
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

    private function createPublishEvent(int $contentId, int $versionNo): PublishVersionEvent
    {
        $versionInfo = $this->createMock(VersionInfo::class);
        $versionInfo->method('getVersionNo')->willReturn($versionNo);
        $content = $this->createMock(Content::class);
        $content->method('getId')->willReturn($contentId);

        return new PublishVersionEvent($content, $versionInfo, ['eng-GB']);
    }
}