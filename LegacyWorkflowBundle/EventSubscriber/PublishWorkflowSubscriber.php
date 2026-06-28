<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\EventSubscriber;

use Haeretici\LegacyWorkflowBundle\Workflow\Exception\WorkflowHaltedException;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerResultEvaluator;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Ibexa\Contracts\Core\Repository\Events\Content\BeforePublishVersionEvent;
use Ibexa\Contracts\Core\Repository\Events\Content\PublishVersionEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PublishWorkflowSubscriber implements EventSubscriberInterface
{
    public const OPERATION_CONTENT_PUBLISH = 'content_publish';
    public const MODULE_CONTENT = 'content';
    public const FUNCTION_PUBLISH = 'publish';
    public const TRIGGER_PRE_PUBLISH = 'pre_publish';
    public const TRIGGER_POST_PUBLISH = 'post_publish';

    /** @var array{Status: int, Result: mixed, WorkflowProcess?: mixed}|null */
    private ?array $lastResult = null;

    public function __construct(
        private readonly TriggerRunner $triggerRunner,
        private readonly PermissionResolver $permissionResolver,
        private readonly bool $enabled = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforePublishVersionEvent::class => 'onBeforePublishVersion',
            PublishVersionEvent::class => 'onPublishVersion',
        ];
    }

    public function onBeforePublishVersion(BeforePublishVersionEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->applyTriggerResult(
            $this->executePublishTrigger(
                self::TRIGGER_PRE_PUBLISH,
                $event->getVersionInfo()->getContentInfo()->getId(),
                $event->getVersionInfo()->getVersionNo()
            ),
            haltOnBlocking: true
        );
    }

    public function onPublishVersion(PublishVersionEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->applyTriggerResult(
            $this->executePublishTrigger(
                self::TRIGGER_POST_PUBLISH,
                $event->getContent()->getId(),
                $event->getVersionInfo()->getVersionNo()
            ),
            haltOnBlocking: false
        );
    }

    /** @return array{Status: int, Result: mixed, WorkflowProcess?: mixed}|null */
    public function getLastResult(): ?array
    {
        return $this->lastResult;
    }

    /** @return array{Status: int, Result: mixed, WorkflowProcess?: mixed} */
    private function executePublishTrigger(string $triggerName, int $objectId, int $version): array
    {
        return $this->triggerRunner->runTrigger(
            $triggerName,
            self::MODULE_CONTENT,
            self::FUNCTION_PUBLISH,
            [
                'object_id' => $objectId,
                'version' => $version,
                'user_id' => $this->permissionResolver->getCurrentUserReference()->getUserId(),
                'operation' => self::OPERATION_CONTENT_PUBLISH,
            ],
            ['object_id', 'version']
        );
    }

    /** @param array{Status: int, Result: mixed, WorkflowProcess?: mixed} $result */
    private function applyTriggerResult(array $result, bool $haltOnBlocking): void
    {
        $this->lastResult = $result;

        if (!$haltOnBlocking) {
            return;
        }

        if (TriggerResultEvaluator::shouldCancelOperation($result['Status'])) {
            throw new WorkflowHaltedException($result, 'Workflow cancelled content publish operation');
        }

        if (TriggerResultEvaluator::shouldHaltOperation($result['Status'])) {
            throw new WorkflowHaltedException($result, 'Workflow halted content publish operation');
        }
    }
}